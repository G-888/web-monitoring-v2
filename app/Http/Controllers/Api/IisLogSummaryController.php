<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IisLogSummary;
use App\Models\IisSuspiciousEvent;
use App\Models\Server;
use App\Services\AgentDeploymentService;
use App\Services\ServerAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IisLogSummaryController extends Controller
{
    public function store(Request $request, AgentDeploymentService $deployment, ServerAlertService $alerts): JsonResponse
    {
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
            'top_ips' => ['nullable', 'array'],
            'top_urls' => ['nullable', 'array'],
            'parser_errors' => ['nullable', 'array', 'max:20'],
            'parser_errors.*' => ['nullable', 'string', 'max:1000'],
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
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if (!$server->is_active) {
            return response()->json(['error' => 'Server is not registered or active'], 403);
        }

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

        if (empty($expectedKey)) {
            return false;
        }

        return hash_equals($expectedKey, $apiKey);
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

    private function evaluateAlerts(Server $server, IisLogSummary $summary, ServerAlertService $alerts): void
    {
        if (!$server->alerts_enabled || $server->isUnderMaintenance()) {
            return;
        }

        $rules = [
            'http_500_spike' => [
                'value' => (int) $summary->http_500,
                'threshold' => (int) config('agent.iis_logs.alerts.http_500_threshold', 10),
            ],
            'http_404_spike' => [
                'value' => (int) $summary->http_404,
                'threshold' => (int) config('agent.iis_logs.alerts.http_404_threshold', 50),
            ],
            'suspicious_event_spike' => [
                'value' => (int) $summary->suspicious_count,
                'threshold' => (int) config('agent.iis_logs.alerts.suspicious_threshold', 5),
            ],
        ];

        foreach ($rules as $rule => $data) {
            if ($data['threshold'] <= 0 || $data['value'] < $data['threshold']) {
                continue;
            }

            $cacheKey = "iis_log_alert:{$server->id}:{$rule}";
            $cooldown = (int) config('agent.iis_logs.alerts.cooldown_seconds', $server->alert_cooldown_seconds ?? 900);

            if (Cache::has($cacheKey)) {
                continue;
            }

            Cache::put($cacheKey, true, max(60, $cooldown));

            $alerts->sendIisLogAlert($server, $rule, $data['value'], $data['threshold'], [
                'window_start' => $summary->window_start?->format('Y-m-d H:i:s'),
                'window_end' => $summary->window_end?->format('Y-m-d H:i:s'),
                'total_requests' => $summary->total_requests,
            ]);
        }
    }
}
