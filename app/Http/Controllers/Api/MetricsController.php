<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessServerMetric;
use App\Models\Server;
use App\Models\WindowsServiceCommand;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class MetricsController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // Validate API key
        $apiKey = $request->header('X-API-Key');
        if (!$apiKey || !$this->validateApiKey($apiKey)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Validate request data
        $validated = $request->validate([
            'server_id' => 'required|string|max:255',
            'cpu' => 'required|numeric|min:0|max:100',
            'ram_used' => 'required|numeric|min:0',
            'ram_total' => 'required|numeric|min:0.01',
            'disk_used' => 'required|numeric|min:0',
            'disk_total' => 'required|numeric|min:0.01',
            'timestamp' => 'required|date',
            'agent_version' => 'nullable|string|max:255',
            'services' => 'nullable|array|max:100',
            'services.*.name' => 'required_with:services|string|max:255',
            'services.*.display_name' => 'nullable|string|max:255',
            'services.*.status' => 'required_with:services|string|max:100',
            'services.*.startup_type' => 'nullable|string|max:100',
            'command_results' => 'nullable|array|max:100',
            'command_results.*.id' => 'required_with:command_results|integer',
            'command_results.*.status' => 'required_with:command_results|string|in:succeeded,failed',
            'command_results.*.output' => 'nullable|string|max:2000',
            'command_results.*.error' => 'nullable|string|max:2000',
        ]);

        // Rate limiting: max 12 requests per minute per server (every 5 seconds)
        $serverId = $validated['server_id'];
        $cacheKey = "metrics_rate_limit_{$serverId}";

        if (RateLimiter::tooManyAttempts($cacheKey, 12)) {
            return response()->json(['error' => 'Rate limit exceeded'], 429);
        }

        RateLimiter::hit($cacheKey, 60);

        $server = Server::where('server_id', $serverId)->first();

        if (!$server || !$server->is_active) {
            return response()->json(['error' => 'Server is not registered or active'], 403);
        }

        if (! empty($validated['agent_version'])) {
            $server->forceFill(['agent_version' => $validated['agent_version']])->save();
        }

        try {
            $commands = $this->claimCommands($server);
            $monitoredServices = $this->monitoredServices($server);

            // Dispatch job to process metric asynchronously
            ProcessServerMetric::dispatch($validated);

            return response()->json([
                'status' => 'accepted',
                'commands' => $commands,
                'monitored_services' => $monitoredServices,
            ], 202);

        } catch (\Exception $e) {
            Log::error('Failed to queue server metric', [
                'server_id' => $serverId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    private function validateApiKey(string $apiKey): bool
    {
        $expectedKey = config('services.agent.key');

        if (empty($expectedKey)) {
            return false;
        }

        return hash_equals($expectedKey, $apiKey);
    }

    private function claimCommands(Server $server): array
    {
        return WindowsServiceCommand::query()
            ->where('server_id', $server->id)
            ->where('status', WindowsServiceCommand::STATUS_QUEUED)
            ->oldest()
            ->limit(5)
            ->get()
            ->map(function (WindowsServiceCommand $command) {
                $command->forceFill([
                    'status' => WindowsServiceCommand::STATUS_RUNNING,
                    'picked_up_at' => now(),
                ])->save();

                return [
                    'id' => $command->id,
                    'service_name' => $command->service_name,
                    'action' => $command->action,
                ];
            })
            ->values()
            ->all();
    }

    private function monitoredServices(Server $server): array
    {
        return $server->windowsServices()
            ->where('is_monitored', true)
            ->orderBy('service_name')
            ->pluck('service_name')
            ->values()
            ->all();
    }
}
