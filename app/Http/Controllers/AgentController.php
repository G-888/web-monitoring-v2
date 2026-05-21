<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Services\AgentDeploymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class AgentController extends Controller
{
    public function index()
    {
        $servers = Server::with([
            'applications',
            'latestMetric',
            'iisLogCollectorStatus',
            'windowsServices' => fn ($query) => $query->where('is_monitored', true),
        ])
            ->orderBy('group')
            ->orderBy('name')
            ->get();

        $stats = [
            'total' => $servers->count(),
            'online' => $servers->filter(fn (Server $server) => $server->agentHeartbeatStatus() === 'online')->count(),
            'outdated' => $servers->filter(fn (Server $server) => in_array($server->agentVersionState(), ['outdated', 'unsupported'], true))->count(),
            'errors' => $servers->filter(fn (Server $server) => filled($server->last_agent_error))->count(),
        ];

        return view('agents.index', compact('servers', 'stats'));
    }

    public function downloadConfig(Request $request, Server $server, AgentDeploymentService $deployment)
    {
        $server = $this->resolveServer($request, $server);

        abort_unless($server->exists, 404);

        $plainKey = $deployment->generatePlainKey($server);
        $options = $deployment->normalizeOptions($request, $server);
        $config = $deployment->buildConfig($server, $plainKey, $options);
        $deployment->audit($server, 'config_generated', [
            'profile' => $config['deploymentProfile'] ?? null,
            'feature_flags' => $config['featureFlags'],
            'windows_services' => $config['windowsServices'],
            'auto_update' => $config['autoUpdate']['enabled'] ?? false,
            'manual_override' => (bool) ($options['manualOverride'] ?? false),
        ], $request);

        if ($options['manualOverride'] ?? false) {
            $deployment->audit($server, 'agent_profile_manual_override', [
                'profile' => $config['deploymentProfile'] ?? null,
                'feature_flags' => $config['featureFlags'],
                'windows_services' => $config['windowsServices'],
            ], $request);
        }

        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $filename = "agent-config-{$server->server_id}.json";

        return Response::make($json, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function downloadPackage(Request $request, Server $server, AgentDeploymentService $deployment)
    {
        $server = $this->resolveServer($request, $server);
        abort_unless($server->exists, 404);

        $plainKey = $deployment->generatePlainKey($server);
        $options = $deployment->normalizeOptions($request, $server);
        $config = $deployment->buildConfig($server, $plainKey, $options);
        $zipPath = $deployment->createPackage($server, $config);
        $filename = $deployment->packageFilename($server);

        $deployment->audit($server, 'package_downloaded', [
            'filename' => $filename,
            'profile' => $config['deploymentProfile'] ?? null,
            'feature_flags' => $config['featureFlags'],
            'windows_services' => $config['windowsServices'],
            'auto_update' => $config['autoUpdate']['enabled'] ?? false,
            'manual_override' => (bool) ($options['manualOverride'] ?? false),
        ], $request);

        if ($options['manualOverride'] ?? false) {
            $deployment->audit($server, 'agent_profile_manual_override', [
                'profile' => $config['deploymentProfile'] ?? null,
                'feature_flags' => $config['featureFlags'],
                'windows_services' => $config['windowsServices'],
            ], $request);
        }

        return response()->download($zipPath, $filename)->deleteFileAfterSend(true);
    }

    public function rotateKey(Request $request, Server $server, AgentDeploymentService $deployment)
    {
        $server = $this->resolveServer($request, $server);
        abort_unless($server->exists, 404);

        $deployment->generatePlainKey($server);
        $deployment->audit($server, 'agent_key_rotated', [], $request);

        return redirect()->back()
            ->with('success', 'Agent key rotated. Download a new config or package before restarting the agent.');
    }

    private function resolveServer(Request $request, Server $server): Server
    {
        if ($server->exists) {
            return $server;
        }

        $routeServer = $request->route('server');

        if ($routeServer instanceof Server) {
            return $routeServer;
        }

        return Server::query()
            ->whereKey($routeServer)
            ->orWhere('server_id', $routeServer)
            ->firstOrFail();
    }

    public function preview(Server $server, AgentDeploymentService $deployment)
    {
        return $deployment->buildConfig($server, '<generated-on-download>');
    }
}
