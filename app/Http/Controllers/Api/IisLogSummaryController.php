<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IisLogCollectorStatus;
use App\Models\IisLogSummary;
use App\Models\IisSuspiciousEvent;
use App\Models\Server;
use App\Services\AgentDeploymentService;
use App\Services\ServerAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class IisLogSummaryController extends Controller
{
    public function store(Request $request, AgentDeploymentService $deployment, ServerAlertService $alerts): JsonResponse
    {
        if (! $this->payloadWithinLimit($request, (int) config('agent.ingest_limits.iis_log_summary_max_bytes', 1048576))) {
            return response()->json(['error' => 'Payload too large'], 413);
        }

        $apiKey = $request->header('X-API-Key');
        if (!$apiKey) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'server_id' => ['required', 'string', 'max:255'],
            'window_start' => ['nullable', 'date'],
            'window_end' => ['nullable', 'date'],
            'files_scanned' => ['nullable', 'integer', 'min:0'],
            'lines_scanned' => ['nullable', 'integer', 'min:0'],
            'total_requests' => ['required', 'integer', 'min:0'],
            'status_2xx' => ['nullable', 'integer', 'min:0'],
            'status_3xx' => ['nullable', 'integer', 'min:0'],
            'status_4xx' => ['nullable', 'integer', 'min:0'],
            'status_5xx' => ['nullable', 'integer', 'min:0'],
            'http_404' => ['nullable', 'integer', 'min:0'],
            'http_500' => ['nullable', 'integer', 'min:0'],
            'suspicious_count' => ['nullable', 'integer', 'min:0'],
            'top_ips' => ['nullable', 'array', 'max:50'],
            'top_ips.*.value' => ['nullable', 'string', 'max:255'],
            'top_ips.*.count' => ['nullable', 'integer', 'min:0'],
            'top_urls' => ['nullable', 'array', 'max:50'],
            'top_urls.*.value' => ['nullable', 'string', 'max:4000'],
            'top_urls.*.count' => ['nullable', 'integer', 'min:0'],
            'parser_errors' => ['nullable', 'array', 'max:20'],
            'parser_errors.*' => ['nullable', 'string', 'max:1000'],
            'collector_health' => ['nullable', 'array'],
            'collector_health.enabled' => ['nullable', 'boolean'],
            'collector_health.last_scan_at' => ['nullable', 'date'],
            'collector_health.files_seen' => ['nullable', 'integer', 'min:0'],
            'collector_health.files_read' => ['nullable', 'integer', 'min:0'],
            'collector_health.lines_read' => ['nullable', 'integer', 'min:0'],
            'collector_health.summaries_sent' => ['nullable', 'integer', 'min:0'],
            'collector_health.last_error' => ['nullable', 'string', 'max:2000'],
            'collector_health.state_file_path' => ['nullable', 'string', 'max:1000'],
            'suspicious_samples' => ['nullable', 'array', 'max:50'],
            'suspicious_samples.*.timestamp' => ['nullable', 'date'],
            'suspicious_samples.*.ip' => ['nullable', 'string', 'max:255'],
            'suspicious_samples.*.ip_address' => ['nullable', 'string', 'max:255'],
            'suspicious_samples.*.method' => ['nullable', 'string', 'max:20'],
            'suspicious_samples.*.url' => ['nullable', 'string', 'max:4000'],
            'suspicious_samples.*.status_code' => ['nullable', 'integer', 'min:100', 'max:599'],
            'suspicious_samples.*.matched_pattern' => ['nullable', 'string', 'max:255'],
            'suspicious_samples.*.pattern' => ['nullable', 'string', 'max:255'],
            'suspicious_samples.*.user_agent' => ['nullable', 'string', 'max:2000'],
            'suspicious_samples.*.raw' => ['nullable', 'string', 'max:4000'],
        ]);

        $server = Server::where('server_id', $validated['server_id'])->first();

        if (!$server || !$this->validateApiKey($apiKey, $server, $deployment)) {
            $this->logFailedAuthentication($request, $validated['server_id'], (bool) $server);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if (!$server->is_active) {
            return response()->json(['error' => 'Server is not registered or active'], 403);
        }

        if ($this->rateLimited($validated['server_id'])) {
            return response()->json(['error' => 'Rate limit exceeded'], 429);
        }

        $validated = $this->applyAllowlist($validated);

        $summary = DB::transaction(function () use ($server, $validated) {
            $summary = IisLogSummary::create([
                'server_id' => $server->id,
                'agent_server_id' => $validated['server_id'],
                'window_start' => $validated['window_start'] ?? null,
                'window_end' => $validated['window_end'] ?? null,
                'files_scanned' => $validated['files_scanned'] ?? 0,
                'lines_scanned' => $validated['lines_scanned'] ?? 0,
                'total_requests' => $validated['total_requests'],
                'status_2xx' => $validated['status_2xx'] ?? 0,
                'status_3xx' => $validated['status_3xx'] ?? 0,
                'status_4xx' => $validated['status_4xx'] ?? 0,
                'status_5xx' => $validated['status_5xx'] ?? 0,
                'http_404' => $validated['http_404'] ?? 0,
                'http_500' => $validated['http_500'] ?? 0,
                'suspicious_count' => $validated['suspicious_count'] ?? 0,
                'top_ips' => $validated['top_ips'] ?? [],
                'top_urls' => $validated['top_urls'] ?? [],
                'parser_errors' => $validated['parser_errors'] ?? [],
            ]);

            foreach ($validated['suspicious_samples'] ?? [] as $sample) {
                IisSuspiciousEvent::create([
                    'server_id' => $server->id,
                    'iis_log_summary_id' => $summary->id,
                    'event_timestamp' => $sample['timestamp'] ?? null,
                    'ip_address' => $sample['ip_address'] ?? $sample['ip'] ?? null,
                    'method' => $sample['method'] ?? null,
                    'url' => $sample['url'] ?? null,
                    'status_code' => $sample['status_code'] ?? null,
                    'matched_pattern' => $sample['matched_pattern'] ?? $sample['pattern'] ?? null,
                    'user_agent' => $sample['user_agent'] ?? null,
                    'raw' => $sample['raw'] ?? null,
                ]);
            }

            return $summary;
        });

        $this->syncCollectorHealth($server, $validated['collector_health'] ?? null);
        $this->syncParserErrors($server, $validated['parser_errors'] ?? []);
        $this->evaluateAlerts($server, $summary, $alerts);

        return response()->json(['status' => 'accepted'], 202);
    }

    private function validateApiKey(string $apiKey, Server $server, AgentDeploymentService $deployment): bool
    {
        if ($server->agent_api_key_hash && $deployment->keyMatches($server, $apiKey)) {
            return true;
        }

        $expectedKey = config('services.agent.key');

        if (! config('agent.global_api_key_enabled', true) || empty($expectedKey)) {
            return false;
        }

        return hash_equals($expectedKey, $apiKey);
    }

    private function payloadWithinLimit(Request $request, int $maxBytes): bool
    {
        $contentLength = (int) $request->server('CONTENT_LENGTH', 0);

        return $maxBytes <= 0 || $contentLength <= 0 || $contentLength <= $maxBytes;
    }

    private function rateLimited(string $serverId): bool
    {
        $cacheKey = "iis_log_summary_rate_limit_{$serverId}";
        $maxAttempts = (int) config('agent.rate_limits.iis_log_summaries_per_minute', 20);

        if (RateLimiter::tooManyAttempts($cacheKey, $maxAttempts)) {
            return true;
        }

        RateLimiter::hit($cacheKey, 60);

        return false;
    }

    private function logFailedAuthentication(Request $request, string $serverId, bool $serverExists): void
    {
        Log::warning('Agent IIS log authentication failed', [
            'server_id' => $serverId,
            'server_exists' => $serverExists,
            'ip' => $request->ip(),
        ]);
    }

    private function syncParserErrors(Server $server, array $parserErrors): void
    {
        if ($parserErrors === []) {
            return;
        }

        $server->forceFill([
            'last_agent_error' => 'IIS log parsing: '.Str::limit((string) $parserErrors[0], 900),
        ])->save();
    }

    private function syncCollectorHealth(Server $server, ?array $health): void
    {
        if (! is_array($health)) {
            return;
        }

        IisLogCollectorStatus::updateOrCreate(
            ['server_id' => $server->id],
            [
                'enabled' => (bool) ($health['enabled'] ?? false),
                'last_scan_at' => $health['last_scan_at'] ?? null,
                'files_seen' => (int) ($health['files_seen'] ?? 0),
                'files_read' => (int) ($health['files_read'] ?? 0),
                'lines_read' => (int) ($health['lines_read'] ?? 0),
                'summaries_sent' => (int) ($health['summaries_sent'] ?? 0),
                'last_error' => $health['last_error'] ?? null,
                'state_file_path' => $health['state_file_path'] ?? null,
            ]
        );

        if (! empty($health['last_error'])) {
            $server->forceFill([
                'last_agent_error' => 'IIS collector: '.Str::limit((string) $health['last_error'], 900),
            ])->save();
        }
    }

    private function applyAllowlist(array $validated): array
    {
        $samples = $validated['suspicious_samples'] ?? [];

        if ($samples === []) {
            return $validated;
        }

        $filtered = collect($samples)
            ->reject(fn (array $sample) => $this->isAllowlistedSample($sample))
            ->values()
            ->all();

        $removed = count($samples) - count($filtered);

        if ($removed > 0) {
            $validated['suspicious_samples'] = $filtered;
            $validated['suspicious_count'] = max(0, (int) ($validated['suspicious_count'] ?? 0) - $removed);
        }

        return $validated;
    }

    private function isAllowlistedSample(array $sample): bool
    {
        $allowlist = config('agent.iis_logs.allowlist', []);
        $ip = strtolower((string) ($sample['ip_address'] ?? $sample['ip'] ?? ''));
        $url = strtolower((string) ($sample['url'] ?? ''));
        $userAgent = strtolower((string) ($sample['user_agent'] ?? ''));

        foreach ((array) ($allowlist['ip_addresses'] ?? []) as $allowedIp) {
            if ($ip !== '' && hash_equals(strtolower(trim((string) $allowedIp)), $ip)) {
                return true;
            }
        }

        foreach ((array) ($allowlist['url_path_contains'] ?? []) as $fragment) {
            $fragment = strtolower(trim((string) $fragment));
            if ($fragment !== '' && str_contains($url, $fragment)) {
                return true;
            }
        }

        foreach ((array) ($allowlist['user_agents'] ?? []) as $fragment) {
            $fragment = strtolower(trim((string) $fragment));
            if ($fragment !== '' && str_contains($userAgent, $fragment)) {
                return true;
            }
        }

        return false;
    }

    private function evaluateAlerts(Server $server, IisLogSummary $summary, ServerAlertService $alerts): void
    {
        if (!$server->alerts_enabled || $server->isUnderMaintenance()) {
            return;
        }

        $metrics = [
            'http_500_spike' => [
                'value' => (int) $summary->http_500,
                'warning' => $this->alertThreshold($server, 'iis_http_500_warning_threshold', 'http_500_warning', 5),
                'critical' => $this->alertThreshold($server, 'iis_http_500_critical_threshold', 'http_500_critical', 10, 'http_500_threshold'),
            ],
            'http_404_spike' => [
                'value' => (int) $summary->http_404,
                'warning' => $this->alertThreshold($server, 'iis_http_404_warning_threshold', 'http_404_warning', 25),
                'critical' => $this->alertThreshold($server, 'iis_http_404_critical_threshold', 'http_404_critical', 50, 'http_404_threshold'),
            ],
            'suspicious_event_spike' => [
                'value' => (int) $summary->suspicious_count,
                'warning' => $this->alertThreshold($server, 'iis_suspicious_warning_threshold', 'suspicious_warning', 3),
                'critical' => $this->alertThreshold($server, 'iis_suspicious_critical_threshold', 'suspicious_critical', 5, 'suspicious_threshold'),
            ],
        ];

        foreach ($metrics as $baseRule => $data) {
            $severity = null;
            $threshold = 0;

            if ($data['critical'] > 0 && $data['value'] >= $data['critical']) {
                $severity = 'critical';
                $threshold = $data['critical'];
            } elseif ($data['warning'] > 0 && $data['value'] >= $data['warning']) {
                $severity = 'warning';
                $threshold = $data['warning'];
            }

            if (! $severity) {
                continue;
            }

            $rule = "{$baseRule}_{$severity}";
            $cacheKey = "iis_log_alert:{$server->id}:{$rule}";
            $cooldown = (int) ($server->iis_alert_cooldown_seconds
                ?? config('agent.iis_logs.alerts.cooldown_seconds', $server->alert_cooldown_seconds ?? 900));

            if (Cache::has($cacheKey)) {
                continue;
            }

            Cache::put($cacheKey, true, max(60, $cooldown));

            $alerts->sendIisLogAlert($server, $rule, $data['value'], $threshold, [
                'severity' => $severity,
                'window_start' => $summary->window_start?->format('Y-m-d H:i:s'),
                'window_end' => $summary->window_end?->format('Y-m-d H:i:s'),
                'total_requests' => $summary->total_requests,
            ]);
        }
    }

    private function alertThreshold(Server $server, string $serverField, string $configKey, int $default, ?string $legacyConfigKey = null): int
    {
        if ($server->{$serverField} !== null) {
            return (int) $server->{$serverField};
        }

        if ($legacyConfigKey && config()->has("agent.iis_logs.alerts.{$legacyConfigKey}")) {
            return (int) config("agent.iis_logs.alerts.{$legacyConfigKey}");
        }

        return (int) config("agent.iis_logs.alerts.{$configKey}", $default);
    }
}
