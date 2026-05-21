<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Server;
use Illuminate\Support\Collection;

class AgentProfileResolver
{
    public const PROFILE_APP = 'windows_iis_coldfusion_app';
    public const PROFILE_DATABASE = 'windows_mysql_db';
    public const PROFILE_APP_DATABASE = 'windows_app_db';
    public const PROFILE_WORKER = 'worker_scheduler';
    public const PROFILE_CUSTOM = 'custom';

    public const IIS_SERVICES = [
        'W3SVC',
        'WAS',
        'IISADMIN',
        'ColdFusion 2023 Application Server',
    ];

    public const MYSQL_SERVICES = [
        'MySQL80',
    ];

    public static function deploymentProfiles(): array
    {
        return [
            self::PROFILE_APP => 'Windows IIS/ColdFusion App Server',
            self::PROFILE_DATABASE => 'Windows MySQL DB Server',
            self::PROFILE_APP_DATABASE => 'Windows App + DB Server',
            self::PROFILE_WORKER => 'Worker/Scheduler Server',
            self::PROFILE_CUSTOM => 'Custom',
        ];
    }

    public function resolve(Server $server): array
    {
        $applications = $this->mappedApplications($server);
        $roles = collect($applications
            ->map(fn (Application $application) => (string) $application->pivot?->role)
            ->all())
            ->filter()
            ->unique()
            ->values();

        $featureFlags = config('agent.feature_flags', []);
        $services = collect();

        $hasAppRole = $roles->intersect(['web', 'application'])->isNotEmpty();
        $hasDatabaseRole = $roles->contains('database');
        $hasWorkerRole = $roles->intersect(['worker', 'scheduler'])->isNotEmpty();
        $hasFileStorageRole = $roles->contains('file_storage');

        if ($roles->isNotEmpty()) {
            $featureFlags['systemMetrics'] = true;
            $featureFlags['windowsServices'] = true;

            if ($hasAppRole) {
                $featureFlags['iisLogs'] = true;
                $featureFlags['networkChecks'] = true;
                $services = $services->merge(self::IIS_SERVICES);
            }

            if ($hasDatabaseRole) {
                $featureFlags['databaseCheck'] = true;
                $featureFlags['networkChecks'] = true;
                $services = $services->merge(self::MYSQL_SERVICES);

                if (! $hasAppRole) {
                    $featureFlags['iisLogs'] = false;
                }
            }

            if ($hasWorkerRole) {
                $featureFlags['scheduledJobs'] = true;
                $featureFlags['networkChecks'] = true;
            }

            if ($hasFileStorageRole) {
                $featureFlags['backupMonitoring'] = true;
                $featureFlags['networkChecks'] = true;
            }
        }

        $profileKey = $this->profileKey($hasAppRole, $hasDatabaseRole, $hasWorkerRole, $roles);

        return [
            'profile_key' => $profileKey,
            'profile_name' => self::deploymentProfiles()[$profileKey],
            'roles' => $roles->all(),
            'applications' => $applications
                ->groupBy('id')
                ->map(fn (Collection $rows) => [
                    'id' => $rows->first()->id,
                    'name' => $rows->first()->name,
                    'roles' => $rows
                        ->map(fn (Application $application) => (string) $application->pivot?->role)
                        ->filter()
                        ->unique()
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
            'featureFlags' => $featureFlags,
            'windowsServices' => $services->filter()->unique()->values()->all(),
            'enabledModules' => collect($featureFlags)
                ->filter(fn ($enabled) => (bool) $enabled)
                ->keys()
                ->values()
                ->all(),
            'is_mapping_based' => $roles->isNotEmpty(),
        ];
    }

    private function mappedApplications(Server $server): Collection
    {
        if ($server->relationLoaded('applications')) {
            return $server->applications;
        }

        return $server->applications()->get();
    }

    private function profileKey(bool $hasAppRole, bool $hasDatabaseRole, bool $hasWorkerRole, Collection $roles): string
    {
        if ($hasAppRole && $hasDatabaseRole) {
            return self::PROFILE_APP_DATABASE;
        }

        if ($hasAppRole) {
            return self::PROFILE_APP;
        }

        if ($hasDatabaseRole) {
            return self::PROFILE_DATABASE;
        }

        if ($hasWorkerRole) {
            return self::PROFILE_WORKER;
        }

        if ($roles->isNotEmpty()) {
            return self::PROFILE_CUSTOM;
        }

        return self::PROFILE_CUSTOM;
    }
}
