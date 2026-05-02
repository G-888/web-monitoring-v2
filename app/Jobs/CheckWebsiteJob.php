<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

use App\Models\CheckResult;
use App\Models\SeoResult;
use App\Models\Monitor;
use App\Services\SearchEngineSeoScanner;

use App\Mail\MonitorDown;
use App\Mail\MonitorRecovered;

use App\Events\MonitorChecked;
use App\Jobs\SendTelegramNotification;

class CheckWebsiteJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public Monitor $monitor, public bool $force = false) {}

    public function handle()
    {
        $this->monitor->refresh();

        if (! $this->force && ! $this->monitor->is_active) {
            return;
        }

        $this->checkSsl();

        $start = microtime(true);
        $html = '';

        try {
            $response = Http::timeout(5)
                ->withoutVerifying()
                ->get($this->monitor->url);

            $status = $response->status();
            $isUp = $status >= 200 && $status < 400;
            $html = $response->body();

        } catch (\Exception $e) {
            $status = null;
            $isUp = false;

            Log::error("Monitor error: {$this->monitor->url} | " . $e->getMessage());
        }

        $time = microtime(true) - $start;

        $detected = [];
        $isSuspicious = false;

        if ($this->monitor->seo_enabled) {
            $searchScan = app(SearchEngineSeoScanner::class)->scan($this->monitor);
            $searchFindings = $searchScan['findings'] ?? [];
            $searchDetected = $searchScan['detected_patterns'] ?? [];
            $searchQueries = collect($searchFindings)
                ->pluck('query')
                ->unique()
                ->values()
                ->all();

            $html = $html ?: ($response->body() ?? '');

            if ($html !== '') {
                $clean = strtolower(strip_tags($html));
                $clean = substr($clean, 0, 10000);
                $detected = $this->detectSeoPoisoning($clean);

                $outboundLinks = $this->extractOutboundLinks($html, $this->monitor->url);
                if (count($outboundLinks) > 20) { // Baseline threshold
                    $detected[] = 'excessive_outbound_links';
                }

                if (! $this->monitor->seo_baseline) {
                    $this->monitor->update([
                        'seo_baseline' => $clean
                    ]);
                } else {
                    similar_text($this->monitor->seo_baseline, $clean, $percent);

                    if ($percent < 70) {
                        $detected[] = 'content_changed';
                    }
                }
            }

            $detected = array_values(array_unique(array_merge($detected, $searchDetected)));
            $isSuspicious = count($detected) > 0;

            SeoResult::create([
                'monitor_id' => $this->monitor->id,
                'is_suspicious' => $isSuspicious,
                'detected_patterns' => $detected,
                'search_findings' => $searchFindings,
                'search_queries' => $searchQueries,
                'checked_at' => now(),
            ]);

            if ($isSuspicious) {
                Log::warning('SEO poisoning detected', [
                    'url' => $this->monitor->url,
                    'patterns' => $detected
                ]);
            }
        }

        $last = CheckResult::where('monitor_id', $this->monitor->id)
            ->latest('checked_at')
            ->first();

        CheckResult::create([
            'monitor_id'    => $this->monitor->id,
            'status_code'   => $status,
            'response_time' => $time,
            'is_up'         => $isUp,
            'checked_at'    => now(),
        ]);

        // Alerts
        $emails = is_array($this->monitor->alert_emails) && count($this->monitor->alert_emails) > 0 
            ? $this->monitor->alert_emails 
            : ['suhailmajemi@gmail.com'];

        if (!$isUp && (!$last || $last->is_up)) {
            foreach ($emails as $email) {
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    Mail::to($email)->send(new MonitorDown($this->monitor));
                }
            }
            $this->dispatchAdvancedAlerts('down');
        }

        if ($last && !$last->is_up && $isUp) {
            foreach ($emails as $email) {
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    Mail::to($email)->send(new MonitorRecovered($this->monitor));
                }
            }
            $this->dispatchAdvancedAlerts('recovered');
        }

        // 🔥 BROADCAST
        try {
            broadcast(new MonitorChecked([
                'id' => $this->monitor->id,
                'is_up' => $isUp,
                'status_code' => $status,
                'response_time' => round($time, 3),
                'uptime_24h' => method_exists($this->monitor, 'uptimePercentage')
                    ? $this->monitor->uptimePercentage()
                    : 0,
                'seo_suspicious' => $isSuspicious,
            ]));

            Log::info('Monitor event broadcasted', ['id' => $this->monitor->id]);
        } catch (\Throwable $e) {
            Log::warning('Monitor result saved, but realtime broadcast failed', [
                'monitor_id' => $this->monitor->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function detectSeoPoisoning($content)
    {
        $patterns = [
            'casino', 'viagra', 'porn', 'loan', 'betting', 'crypto', 'xxx', 'free money', 'earn cash', 'seo spam',
            'slot', 'pg slot', 'ฝากถอน', 'ไม่มีขั้นต่ำ', 'เว็บตรง', 'สล็อต'
        ];

        $found = [];

        foreach ($patterns as $pattern) {
            if (str_contains($content, $pattern)) {
                $found[] = $pattern;
            }
        }

        return $found;
    }

    private function extractOutboundLinks(string $html, string $monitorUrl): array
    {
        $monitorHost = parse_url($monitorUrl, PHP_URL_HOST) ?: '';
        $monitorHost = preg_replace('/^www\./', '', strtolower($monitorHost));

        preg_match_all('/<a\s+[^>]*href=["\'](http[s]?:\/\/[^"\']+)["\']/i', $html, $matches);
        
        $outbound = [];
        foreach ($matches[1] as $url) {
            $host = parse_url($url, PHP_URL_HOST) ?: '';
            $host = preg_replace('/^www\./', '', strtolower($host));

            if ($host !== '' && $host !== $monitorHost && !str_ends_with($host, '.' . $monitorHost)) {
                $outbound[] = $url;
            }
        }

        return array_unique($outbound);
    }

    private function checkSsl(): void
    {
        if (!str_starts_with($this->monitor->url, 'https://')) {
            return;
        }

        try {
            $urlParts = parse_url($this->monitor->url);
            $host = $urlParts['host'] ?? null;
            if (!$host) return;

            $streamContext = stream_context_create([
                'ssl' => [
                    'capture_peer_cert' => true,
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);

            $client = @stream_socket_client(
                "ssl://{$host}:443", 
                $errno, 
                $errstr, 
                5, 
                STREAM_CLIENT_CONNECT, 
                $streamContext
            );

            if ($client) {
                $params = stream_context_get_params($client);
                if (isset($params['options']['ssl']['peer_certificate'])) {
                    $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate']);
                    
                    if (isset($cert['validTo_time_t'])) {
                        $this->monitor->ssl_expires_at = date('Y-m-d H:i:s', $cert['validTo_time_t']);
                        $this->monitor->ssl_issuer = $cert['issuer']['O'] ?? $cert['issuer']['CN'] ?? null;
                        $this->monitor->save();
                    }
                }
                fclose($client);
            }
        } catch (\Throwable $e) {
            Log::warning('SSL Check failed for ' . $this->monitor->url, ['error' => $e->getMessage()]);
        }
    }

    private function dispatchAdvancedAlerts(string $status): void
    {
        $user = $this->monitor->user;
        if (!$user || !$user->hasDirectPermission('module.advanced_alerts')) {
            return;
        }

        $channels = $user->alertChannels()->where('is_active', true)->get();
        foreach ($channels as $channel) {
            if ($channel->type === 'slack' || $channel->type === 'discord') {
                $title = $status === 'down' ? '🔴 Monitor DOWN: ' : '🟢 Monitor UP: ';
                
                try {
                    Http::timeout(3)->post($channel->endpoint, [
                        'content' => $title . $this->monitor->name . ' (' . $this->monitor->url . ')',
                    ]);
                } catch (\Throwable $e) {
                    Log::error('Failed to send ' . $channel->type . ' alert for monitor ' . $this->monitor->id . ': ' . $e->getMessage());
                }
            } elseif ($channel->type === 'telegram') {
                $title = $status === 'down' ? '🔴 Monitor DOWN' : '🟢 Monitor UP';
                $message = "<b>{$title}</b>\n\n" .
                          "<b>Monitor:</b> {$this->monitor->name}\n" .
                          "<b>URL:</b> {$this->monitor->url}\n" .
                          "<b>Status:</b> " . ucfirst($status) . "\n" .
                          "<b>Time:</b> " . now()->format('Y-m-d H:i:s');

                SendTelegramNotification::dispatch($message);
            }
        }
    }

}
