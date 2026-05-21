<?php

namespace App\Jobs;

use App\Models\Monitor;
use App\Services\OutboundScanGuard;
use App\Models\SeoScan;
use App\Services\SeoScannerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SeoScanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 180;
    public array $backoff = [60, 120, 300];

    public function __construct(protected Monitor $monitor)
    {
        $this->onQueue('security');
    }

    public function handle(SeoScannerService $service, OutboundScanGuard $scanGuard): void
    {
        try {
            $scanGuard->assertAllowed($this->monitor->url);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('SEO scan blocked by outbound scan policy.', [
                'monitor_id' => $this->monitor->id,
                'url' => $this->monitor->url,
            ]);

            return;
        }

        $result = $service->scan($this->monitor->url);

        // Store result
        $scan = new \App\Models\SeoScan();
        $scan->monitor_id = $this->monitor->id;
        $scan->url = $this->monitor->url;
        $scan->status = $result['status'];
        $scan->findings = $result['findings'];
        $scan->diffs = [
            'hashes' => $result['hashes'] ?? [],
            'search_enabled' => $result['search_enabled'] ?? false,
            'search_findings' => $result['search_findings'] ?? [],
            'search_queries' => $result['search_queries'] ?? [],
            'search_detected_patterns' => $result['search_detected_patterns'] ?? [],
        ];
        $scan->scanned_at = now();
        $scan->save();

        // Alert if suspicious
        if ($result['status'] !== 'clean') {
            $this->dispatchAlert($result);
        }
    }

    protected function dispatchAlert(array $result): void
    {
        $type = $result['cloaking'] ? 'SEO_CLOAKING' : 'SEO_SPAM';
        $findings = implode(', ', $result['findings']);
        
        $message = "[$type] Suspicious SEO activity detected on {$this->monitor->name} ({$this->monitor->url}). Findings: {$findings}";

        // Integration with existing alert logic
        $user = $this->monitor->user;
        if ($user) {
            $channels = $user->alertChannels()->where('is_active', true)->get();
            foreach ($channels as $channel) {
                try {
                    Http::post($channel->endpoint, ['content' => $message]);
                } catch (\Exception $e) {
                    Log::error("Failed to send alert: " . $e->getMessage());
                }
            }
        }
    }
}
