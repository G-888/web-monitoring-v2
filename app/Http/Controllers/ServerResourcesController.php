<?php

namespace App\Http\Controllers;

use App\Models\ServerMetric;
use App\Models\Server;
use App\Services\ServerResourcesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServerResourcesController extends Controller
{
    public function index(): View
    {
        return view('server-resources', [
            'initialServers' => app(ServerResourcesService::class)->getSnapshot(),
        ]);
    }

    public function snapshot(ServerResourcesService $serverResources): JsonResponse
    {
        return response()->json($serverResources->getSnapshot());
    }

    public function history(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'server_id' => ['required', 'string', 'max:255'],
            'hours' => ['nullable', 'integer', 'min:1', 'max:168'],
        ]);

        abort_unless(Server::where('server_id', $validated['server_id'])->where('is_active', true)->exists(), 404);

        $hours = (int) ($validated['hours'] ?? 6);

        $metrics = ServerMetric::query()
            ->where('server_id', $validated['server_id'])
            ->where('timestamp', '>=', now()->subHours($hours))
            ->orderByDesc('timestamp')
            ->limit(300)
            ->get()
            ->sortBy('timestamp')
            ->values();

        return response()->json([
            'server_id' => $validated['server_id'],
            'hours' => $hours,
            'labels' => $metrics->map(fn (ServerMetric $metric) => $metric->timestamp->format('H:i'))->values(),
            'cpu' => $metrics->map(fn (ServerMetric $metric) => round((float) $metric->cpu, 1))->values(),
            'ram' => $metrics->map(fn (ServerMetric $metric) => $this->percentage($metric->ram_used, $metric->ram_total))->values(),
            'disk' => $metrics->map(fn (ServerMetric $metric) => $this->percentage($metric->disk_used, $metric->disk_total))->values(),
        ]);
    }

    private function percentage(float|string $used, float|string $total): ?float
    {
        $total = (float) $total;

        if ($total <= 0) {
            return null;
        }

        return round(((float) $used / $total) * 100, 1);
    }
}
