<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NetworkMonitor;
use App\Models\Server;
use App\Services\AgentDeploymentService;
use App\Services\NetworkAlertService;
use App\Services\NetworkCheckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class NetworkCheckResultController extends Controller
{
    public function store(Request $request, AgentDeploymentService $deployment, NetworkCheckService $checks, NetworkAlertService $alerts): JsonResponse
    {
        if (! $this->payloadWithinLimit($request, (int) config('agent.ingest_limits.network_results_max_bytes', 262144))) {
            return response()->json(['error' => 'Payload too large'], 413);
        }

        $apiKey = $request->header('X-API-Key');
        if (! $apiKey) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'server_id' => ['required', 'string', 'max:255'],
            'results' => ['required', 'array', 'max:100'],
            'results.*.monitor_id' => ['required', 'integer', 'exists:network_monitors,id'],
            'results.*.status' => ['required', 'string', 'max:30'],
            'results.*.is_successful' => ['required', 'boolean'],
            'results.*.latency_ms' => ['nullable', 'integer', 'min:0'],
            'results.*.resolved_value' => ['nullable', 'string', 'max:2000'],
            'results.*.expected_value' => ['nullable', 'string', 'max:2000'],
            'results.*.error' => ['nullable', 'string', 'max:2000'],
            'results.*.checked_at' => ['nullable', 'date'],
        ]);

        $server = Server::where('server_id', $validated['server_id'])->first();
        if (! $server || ! $this->validateApiKey($apiKey, $server, $deployment)) {
            $this->logFailedAuthentication($request, $validated['server_id'], (bool) $server);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if (! $server->is_active) {
            return response()->json(['error' => 'Server is not registered or active'], 403);
        }

        if ($this->rateLimited($validated['server_id'])) {
            return response()->json(['error' => 'Rate limit exceeded'], 429);
        }

        foreach ($validated['results'] as $payload) {
            $monitor = NetworkMonitor::query()
                ->whereKey($payload['monitor_id'])
                ->where('source_type', NetworkMonitor::SOURCE_AGENT)
                ->where(function ($query) use ($server) {
                    $query->whereNull('source_server_id')
                        ->orWhere('source_server_id', $server->id);
                })
                ->first();

            if (! $monitor) {
                continue;
            }

            $result = $checks->recordMonitorResult($monitor, [
                ...$payload,
                'source_server_id' => $server->id,
                'checked_at' => $payload['checked_at'] ?? now(),
            ]);
            $alerts->evaluateMonitor($monitor->fresh(['sourceServer']), $result);
        }

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
        $cacheKey = "network_results_rate_limit_{$serverId}";
        $maxAttempts = (int) config('agent.rate_limits.network_results_per_minute', 60);

        if (RateLimiter::tooManyAttempts($cacheKey, $maxAttempts)) {
            return true;
        }

        RateLimiter::hit($cacheKey, 60);

        return false;
    }

    private function logFailedAuthentication(Request $request, string $serverId, bool $serverExists): void
    {
        Log::warning('Agent network result authentication failed', [
            'server_id' => $serverId,
            'server_exists' => $serverExists,
            'ip' => $request->ip(),
        ]);
    }
}
