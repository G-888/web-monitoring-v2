<?php

namespace App\Services;

use App\Models\AgentDeploymentAudit;
use App\Models\Application;
use App\Models\DatabaseMonitor;
use App\Models\NetworkMonitor;
use App\Models\Server;
use Illuminate\Support\Collection;

class ArchitectureOnboardingReviewService
{
    public function __construct(private readonly AgentProfileResolver $profiles)
    {
    }

    public function build(Application $application): array
    {
        $application->load([
            'client',
            'servers',
            'networkMonitors',
            'urls',
        ]);

        $servers = $application->servers->unique('id')->values();
        $appServers = $this->serversForRoles($application, ['web', 'application']);
        $dbServers = $this->serversForRoles($application, ['database']);
        $networkMonitors = NetworkMonitor::query()
            ->with(['sourceServer', 'targetServer'])
            ->where('application_id', $application->id)
            ->orderBy('name')
            ->get();
        $dbMonitors = DatabaseMonitor::query()
            ->with('server')
            ->where('application_id', $application->id)
            ->orderBy('name')
            ->get();

        $deployment = $this->deploymentStatus($servers);
        $drift = $this->profileDrift($servers);
        $checklist = $this->checklist($application, $servers, $networkMonitors, $dbMonitors, $deployment, $drift);
        $completed = collect($checklist)->where('complete', true)->count();
        $score = count($checklist) > 0 ? (int) round(($completed / count($checklist)) * 100) : 0;

        return [
            'client' => $application->client,
            'application' => $application,
            'architecture_type' => $application->architecture_type ?: 'custom',
            'app_servers' => $appServers,
            'db_servers' => $dbServers,
            'network_monitors' => $networkMonitors->where('type', '!=', NetworkMonitor::TYPE_DNS)->values(),
            'dns_monitors' => $networkMonitors->where('type', NetworkMonitor::TYPE_DNS)->values(),
            'db_monitors' => $dbMonitors,
            'deployment' => $deployment,
            'drift' => $drift,
            'checklist' => $checklist,
            'score' => $score,
        ];
    }

    private function serversForRoles(Application $application, array $roles): Collection
    {
        return $application->servers
            ->filter(fn (Server $server) => in_array((string) $server->pivot?->role, $roles, true))
            ->unique('id')
            ->values();
    }

    private function deploymentStatus(Collection $servers): Collection
    {
        $packageServerIds = AgentDeploymentAudit::query()
            ->whereIn('server_id', $servers->pluck('id'))
            ->where('action', 'package_downloaded')
            ->pluck('server_id')
            ->unique();

        return $servers->map(function (Server $server) use ($packageServerIds) {
            $profile = $this->profiles->resolve($server);
            $heartbeat = $server->agentHeartbeatStatus();

            return [
                'server' => $server,
                'expected_profile' => $profile['profile_name'],
                'expected_modules' => $profile['enabledModules'],
                'package_generated' => $packageServerIds->contains($server->id),
                'agent_installed' => $heartbeat !== 'unknown',
                'heartbeat_status' => $heartbeat,
                'agent_version' => $server->agent_version,
                'last_heartbeat_at' => $server->last_heartbeat_at,
            ];
        })->values();
    }

    private function profileDrift(Collection $servers): Collection
    {
        return $servers->map(function (Server $server) {
            $profile = $this->profiles->resolve($server);
            $expectedFlags = collect($profile['featureFlags'] ?? []);
            $reported = collect($server->capabilities ?? [])
                ->map(fn ($capability) => (string) $capability)
                ->filter()
                ->values();

            $expectedEnabled = $expectedFlags->filter(fn ($enabled) => (bool) $enabled)->keys();
            $expectedDisabled = $expectedFlags->filter(fn ($enabled) => ! (bool) $enabled)->keys();
            $missing = $expectedEnabled->diff($reported)->values();
            $unexpected = $reported->intersect($expectedDisabled)->values();
            $reportedProfile = $reported
                ->first(fn (string $capability) => str_starts_with($capability, 'profile:') || str_starts_with($capability, 'deploymentProfile:'));
            $wrongProfile = false;

            if ($reportedProfile) {
                $reportedKey = str_contains($reportedProfile, ':') ? explode(':', $reportedProfile, 2)[1] : $reportedProfile;
                $wrongProfile = $reportedKey !== ($profile['profile_key'] ?? null);
            }

            $status = 'OK';
            if ($wrongProfile) {
                $status = 'Wrong Profile';
            } elseif ($missing->isNotEmpty()) {
                $status = 'Missing Module';
            } elseif ($unexpected->isNotEmpty()) {
                $status = 'Unexpected Module';
            }

            return [
                'server' => $server,
                'expected_profile' => $profile['profile_name'],
                'status' => $status,
                'missing_modules' => $missing->all(),
                'unexpected_modules' => $unexpected->all(),
            ];
        })->values();
    }

    private function checklist(Application $application, Collection $servers, Collection $networkMonitors, Collection $dbMonitors, Collection $deployment, Collection $drift): array
    {
        return [
            ['label' => 'client created', 'complete' => (bool) $application->client_id],
            ['label' => 'application created', 'complete' => $application->exists],
            ['label' => 'servers mapped', 'complete' => $servers->isNotEmpty()],
            ['label' => 'network monitors created', 'complete' => $networkMonitors->isNotEmpty()],
            ['label' => 'DB monitors configured', 'complete' => $dbMonitors->isNotEmpty() && $dbMonitors->every(fn (DatabaseMonitor $monitor) => (bool) $monitor->configured_at)],
            ['label' => 'agent packages generated', 'complete' => $deployment->isNotEmpty() && $deployment->every(fn (array $row) => $row['package_generated'])],
            ['label' => 'agents online', 'complete' => $deployment->isNotEmpty() && $deployment->every(fn (array $row) => $row['heartbeat_status'] === 'online')],
            ['label' => 'profile drift clear', 'complete' => $drift->every(fn (array $row) => $row['status'] === 'OK')],
            ['label' => 'report scope ready', 'complete' => (bool) $application->client_id && $servers->isNotEmpty()],
        ];
    }
}
