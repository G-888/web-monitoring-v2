<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessServerMetric;
use App\Models\ServerMetric;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MetricsController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // Validate API key
        $apiKey = $request->header('X-API-Key');
        if (!$apiKey || !$this->validateApiKey($apiKey)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Rate limiting: max 12 requests per minute per server (every 5 seconds)
        $serverId = $request->input('server_id');
        $cacheKey = "metrics_rate_limit_{$serverId}";
        $requests = Cache::get($cacheKey, 0);

        if ($requests >= 12) {
            return response()->json(['error' => 'Rate limit exceeded'], 429);
        }

        Cache::put($cacheKey, $requests + 1, 60); // 1 minute

        // Validate request data
        $validated = $request->validate([
            'server_id' => 'required|string|max:255',
            'cpu' => 'required|numeric|min:0|max:100',
            'ram_used' => 'required|numeric|min:0',
            'ram_total' => 'required|numeric|min:0',
            'disk_used' => 'required|numeric|min:0',
            'disk_total' => 'required|numeric|min:0',
            'timestamp' => 'required|date',
        ]);

        try {
            // Dispatch job to process metric asynchronously
            ProcessServerMetric::dispatch($validated);

            return response()->json(['status' => 'accepted'], 202);

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
}
