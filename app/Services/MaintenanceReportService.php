<?php

namespace App\Services;

use App\Models\Application;
use App\Models\CheckResult;
use App\Models\DatabaseCheck;
use App\Models\DatabaseMonitor;
use App\Models\IisLogSummary;
use App\Models\Monitor;
use App\Models\NetworkCheckResult;
use App\Models\NetworkMonitor;
use App\Models\SeoScan;
use App\Models\Server;
use App\Models\ServerPortBaseline;
use App\Models\ServerMetric;
use App\Models\WebshellScan;
use App\Models\WindowsServiceCheck;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class MaintenanceReportService
{
    public function build(array $filters): array
    {
        $periodStart = $filters['period_start'];
        $periodEnd = $filters['period_end'];
        $applicationId = $filters['application_id'] ?? null;
        $clientId = $filters['client_id'] ?? null;
        $serverGroup = $filters['server_group'] ?? null;
        $environment = $filters['environment'] ?? null;

        $applications = $this->applications($applicationId, $environment, $clientId);
        $servers = $this->servers($applications, $applicationId, $serverGroup, $clientId);
        $monitors = $this->monitors($applications, $applicationId, $clientId);

        $serverMetrics = $this->serverMetrics($servers, $periodStart, $periodEnd);
        $website = $this->websiteSummary($monitors, $periodStart, $periodEnd);
        $database = $this->databaseSummary($applications, $periodStart, $periodEnd, (bool) ($applicationId || $clientId));
        $windowsServices = $this->windowsServiceSummary($servers, $periodStart, $periodEnd);
        $iis = $this->iisSummary($servers, $periodStart, $periodEnd);
        $network = $this->networkSummary($servers, $applications, $applicationId, $periodStart, $periodEnd);
        $security = $this->securitySummary($monitors, $periodStart, $periodEnd);

        $summary = [
            'generated_at' => now()->toISOString(),
            'scope' => [
                'report_type' => $filters['report_type'],
                'period_start' => $periodStart->toDateTimeString(),
                'period_end' => $periodEnd->toDateTimeString(),
                'application' => $applicationId ? $applications->first()?->only(['id', 'name', 'environment']) : null,
                'client' => $clientId ? \App\Models\Client::find($clientId)?->only(['id', 'name', 'code', 'environment']) : null,
                'server_group' => $serverGroup,
                'environment' => $environment,
                'server_count' => $servers->count(),
                'application_count' => $applications->count(),
                'monitor_count' => $monitors->count(),
            ],
            'executive' => [
                'overall_status' => 'healthy',
                'headline' => 'No critical maintenance issues detected for the selected scope.',
                'key_metrics' => [],
            ],
            'applications' => $this->applicationSummary($applications),
            'servers' => $serverMetrics,
            'website' => $website,
            'ssl' => $this->sslSummary($monitors),
            'database' => $database,
            'windows_services' => $windowsServices,
            'iis' => $iis,
            'network' => $network,
            'incidents' => $this->incidentSummary($website, $database, $windowsServices, $iis, $network),
            'security' => $security,
            'recommendations' => [],
        ];

        $summary['recommendations'] = $this->recommendations($summary);
        $summary['executive'] = $this->executiveSummary($summary);

        return $summary;
    }

    private function applications(?int $applicationId, ?string $environment, ?int $clientId = null): Collection
    {
        return Application::query()
            ->with(['servers.latestMetric', 'servers.iisLogCollectorStatus', 'urls.monitor.latestResult', 'componentRules'])
            ->when($applicationId, fn ($query) => $query->whereKey($applicationId))
            ->when($clientId, fn ($query) => $query->where('client_id', $clientId))
            ->when($environment, fn ($query) => $query->where('environment', $environment))
            ->orderBy('name')
            ->get();
    }

    private function servers(Collection $applications, ?int $applicationId, ?string $serverGroup, ?int $clientId = null): Collection
    {
        if ($applicationId || $clientId) {
            $servers = $applications
                ->flatMap(fn (Application $application) => $application->servers)
                ->unique('id')
                ->values();
        } else {
            $servers = Server::query()
                ->with(['latestMetric', 'iisLogCollectorStatus'])
                ->when($serverGroup, fn ($query) => $query->where('group', $serverGroup))
                ->orderBy('name')
                ->get();
        }

        return $serverGroup
            ? $servers->filter(fn (Server $server) => $server->group === $serverGroup)->values()
            : $servers->values();
    }

    private function monitors(Collection $applications, ?int $applicationId, ?int $clientId = null): Collection
    {
        if (! $applicationId && ! $clientId) {
            return Monitor::query()->with('latestResult')->orderBy('name')->get();
        }

        $monitorIds = $applications
            ->flatMap(fn (Application $application) => $application->urls)
            ->pluck('monitor_id')
            ->filter()
            ->unique()
            ->values();

        return Monitor::query()
            ->with('latestResult')
            ->whereIn('id', $monitorIds)
            ->orderBy('name')
            ->get();
    }

    private function applicationSummary(Collection $applications): array
    {
        return $applications
            ->map(function (Application $application) {
                $health = $application->healthSummary();

                return [
                    'id' => $application->id,
                    'name' => $application->name,
                    'environment' => $application->environment,
                    'status' => $health['status'],
                    'url_status' => $health['url_status'],
                    'app_servers' => $health['app_servers'],
                    'database_servers' => $health['database_servers'],
                    'reasons' => $health['reasons'],
                ];
            })
            ->values()
            ->all();
    }

    private function serverMetrics(Collection $servers, CarbonInterface $periodStart, CarbonInterface $periodEnd): array
    {
        $serverIds = $servers->pluck('server_id')->filter()->values();
        $metricRows = ServerMetric::query()
            ->whereIn('server_id', $serverIds)
            ->whereBetween('timestamp', [$periodStart, $periodEnd])
            ->orderBy('server_id')
            ->orderBy('timestamp')
            ->get()
            ->groupBy('server_id');

        $rows = $servers->map(function (Server $server) use ($metricRows) {
            $metrics = $metricRows->get($server->server_id, collect());

            $cpuAvg = $metrics->avg(fn (ServerMetric $metric) => (float) $metric->cpu);
            $cpuMax = $metrics->max(fn (ServerMetric $metric) => (float) $metric->cpu);
            $ramPercents = $metrics->map(fn (ServerMetric $metric) => (float) $metric->ram_total > 0 ? ((float) $metric->ram_used / (float) $metric->ram_total) * 100 : null)->filter(fn ($value) => $value !== null);
            $diskPercents = $metrics->map(fn (ServerMetric $metric) => (float) $metric->disk_total > 0 ? ((float) $metric->disk_used / (float) $metric->disk_total) * 100 : null)->filter(fn ($value) => $value !== null);

            return [
                'id' => $server->id,
                'server_id' => $server->server_id,
                'name' => $server->name,
                'group' => $server->group,
                'heartbeat' => $server->agentHeartbeatStatus(),
                'last_heartbeat_at' => $server->last_heartbeat_at?->toDateTimeString(),
                'samples' => $metrics->count(),
                'cpu_avg' => $cpuAvg !== null ? round($cpuAvg, 2) : null,
                'cpu_max' => $cpuMax !== null ? round($cpuMax, 2) : null,
                'ram_avg' => $ramPercents->isNotEmpty() ? round($ramPercents->avg(), 2) : null,
                'ram_max' => $ramPercents->isNotEmpty() ? round($ramPercents->max(), 2) : null,
                'disk_avg' => $diskPercents->isNotEmpty() ? round($diskPercents->avg(), 2) : null,
                'disk_max' => $diskPercents->isNotEmpty() ? round($diskPercents->max(), 2) : null,
            ];
        })->values();

        return [
            'online' => $rows->where('heartbeat', 'online')->count(),
            'offline' => $rows->where('heartbeat', 'offline')->count(),
            'unknown' => $rows->where('heartbeat', 'unknown')->count(),
            'rows' => $rows->all(),
        ];
    }

    private function websiteSummary(Collection $monitors, CarbonInterface $periodStart, CarbonInterface $periodEnd): array
    {
        $resultRows = CheckResult::query()
            ->with('monitor')
            ->whereIn('monitor_id', $monitors->pluck('id'))
            ->whereBetween('checked_at', [$periodStart, $periodEnd])
            ->get()
            ->groupBy('monitor_id');

        $rows = $monitors->map(function (Monitor $monitor) use ($resultRows) {
            $results = $resultRows->get($monitor->id, collect());
            $total = $results->count();
            $down = $results->where('is_up', false)->count();

            return [
                'id' => $monitor->id,
                'name' => $monitor->name,
                'url' => $monitor->url,
                'checks' => $total,
                'downtime_count' => $down,
                'uptime_percent' => $total > 0 ? round((($total - $down) / $total) * 100, 2) : null,
                'latest_status' => $monitor->latestResult?->is_up === null ? 'unknown' : ($monitor->latestResult->is_up ? 'up' : 'down'),
            ];
        })->values();

        return [
            'checks' => $rows->sum('checks'),
            'downtime_count' => $rows->sum('downtime_count'),
            'average_uptime' => $rows->whereNotNull('uptime_percent')->isNotEmpty()
                ? round($rows->whereNotNull('uptime_percent')->avg('uptime_percent'), 2)
                : null,
            'rows' => $rows->all(),
        ];
    }

    private function sslSummary(Collection $monitors): array
    {
        $rows = $monitors
            ->filter(fn (Monitor $monitor) => str_starts_with(strtolower($monitor->url), 'https://'))
            ->map(function (Monitor $monitor) {
                $daysLeft = $monitor->ssl_expires_at ? now()->diffInDays($monitor->ssl_expires_at, false) : null;
                $status = $daysLeft === null ? 'unknown' : ($daysLeft < 0 ? 'expired' : ($daysLeft <= ($monitor->ssl_alert_threshold_days ?? 30) ? 'expiring' : 'valid'));

                return [
                    'name' => $monitor->name,
                    'url' => $monitor->url,
                    'expires_at' => $monitor->ssl_expires_at?->toDateString(),
                    'days_left' => $daysLeft,
                    'issuer' => $monitor->ssl_issuer,
                    'status' => $status,
                    'last_error' => $monitor->ssl_last_error,
                ];
            })
            ->values();

        return [
            'total' => $rows->count(),
            'valid' => $rows->where('status', 'valid')->count(),
            'expiring' => $rows->where('status', 'expiring')->count(),
            'expired' => $rows->where('status', 'expired')->count(),
            'unknown' => $rows->where('status', 'unknown')->count(),
            'rows' => $rows->all(),
        ];
    }

    private function databaseSummary(Collection $applications, CarbonInterface $periodStart, CarbonInterface $periodEnd, bool $scopeToApplications = false): array
    {
        $applicationIds = $applications->pluck('id')->filter()->values();

        $checks = DatabaseCheck::query()
            ->with('databaseMonitor')
            ->when($scopeToApplications && $applicationIds->isNotEmpty(), fn ($query) => $query->whereHas('databaseMonitor', fn ($inner) => $inner
                ->whereIn('application_id', $applicationIds)
                ->orWhereNull('application_id')))
            ->whereBetween('checked_at', [$periodStart, $periodEnd])
            ->get();

        $monitors = DatabaseMonitor::query()
            ->with('latestCheck')
            ->when($scopeToApplications && $applicationIds->isNotEmpty(), fn ($query) => $query
                ->whereIn('application_id', $applicationIds)
                ->orWhereNull('application_id'))
            ->orderBy('name')
            ->get();

        return [
            'total_monitors' => $monitors->count(),
            'checks' => $checks->count(),
            'success' => $checks->where('is_up', true)->count(),
            'failures' => $checks->where('is_up', false)->count(),
            'rows' => $monitors->map(fn (DatabaseMonitor $monitor) => [
                'name' => $monitor->name,
                'driver' => $monitor->driver,
                'host' => $monitor->host,
                'last_status' => $monitor->last_status ?? ($monitor->latestCheck?->is_up ? 'up' : ($monitor->latestCheck ? 'down' : 'unknown')),
                'last_checked_at' => $monitor->last_checked_at?->toDateTimeString() ?? $monitor->latestCheck?->checked_at?->toDateTimeString(),
                'last_error' => $monitor->last_error ?? $monitor->latestCheck?->error,
            ])->values()->all(),
        ];
    }

    private function windowsServiceSummary(Collection $servers, CarbonInterface $periodStart, CarbonInterface $periodEnd): array
    {
        $serverIds = $servers->pluck('id');
        $stopped = WindowsServiceCheck::query()
            ->with('windowsService.server')
            ->whereBetween('checked_at', [$periodStart, $periodEnd])
            ->whereHas('windowsService', fn ($query) => $query->whereIn('server_id', $serverIds))
            ->where('status', '!=', 'Running')
            ->latest('checked_at')
            ->limit(100)
            ->get();

        return [
            'stopped_events' => $stopped->count(),
            'rows' => $stopped->map(fn (WindowsServiceCheck $check) => [
                'server' => $check->windowsService?->server?->name,
                'service' => $check->windowsService?->display_name ?: $check->windowsService?->service_name,
                'status' => $check->status,
                'checked_at' => $check->checked_at?->toDateTimeString(),
            ])->values()->all(),
        ];
    }

    private function iisSummary(Collection $servers, CarbonInterface $periodStart, CarbonInterface $periodEnd): array
    {
        $summaries = IisLogSummary::query()
            ->whereIn('server_id', $servers->pluck('id'))
            ->whereBetween('window_end', [$periodStart, $periodEnd])
            ->get();

        return [
            'total_requests' => (int) $summaries->sum('total_requests'),
            'http_404' => (int) $summaries->sum('http_404'),
            'http_500' => (int) $summaries->sum('http_500'),
            'suspicious_count' => (int) $summaries->sum('suspicious_count'),
            'top_ips' => $this->mergeTopValues($summaries, 'top_ips'),
            'top_urls' => $this->mergeTopValues($summaries, 'top_urls'),
        ];
    }

    private function networkSummary(Collection $servers, Collection $applications, ?int $applicationId, CarbonInterface $periodStart, CarbonInterface $periodEnd): array
    {
        $applicationIds = $applicationId
            ? collect([$applicationId])
            : $applications->pluck('id')->filter()->values();

        $monitorQuery = NetworkMonitor::query()
            ->with(['application', 'sourceServer', 'targetServer', 'latestResult'])
            ->when($applicationIds->isNotEmpty(), function ($query) use ($applicationIds, $servers) {
                $query->where(function ($inner) use ($applicationIds, $servers) {
                    $inner->whereIn('application_id', $applicationIds)
                        ->orWhereIn('source_server_id', $servers->pluck('id'))
                        ->orWhereIn('target_server_id', $servers->pluck('id'));
                });
            });

        $monitors = $monitorQuery->orderBy('name')->get();

        $checks = NetworkCheckResult::query()
            ->with('networkMonitor.application')
            ->whereIn('network_monitor_id', $monitors->pluck('id'))
            ->whereBetween('checked_at', [$periodStart, $periodEnd])
            ->get();

        $failed = $checks->where('is_successful', false);
        $dnsMismatches = $checks->whereIn('status', ['mismatch', 'dns_drift']);
        $baselineViolations = ServerPortBaseline::query()
            ->with('server')
            ->whereIn('server_id', $servers->pluck('id'))
            ->whereIn('last_status', ['down', 'unexpected_open', 'error', 'unsupported'])
            ->get();

        return [
            'total_monitors' => $monitors->count(),
            'checks' => $checks->count(),
            'success' => $checks->where('is_successful', true)->count(),
            'failures' => $failed->count(),
            'affected_applications' => $monitors
                ->filter(fn (NetworkMonitor $monitor) => $monitor->application && in_array($monitor->last_status, ['down', 'mismatch', 'dns_drift', 'unexpected_open', 'error'], true))
                ->pluck('application.name')
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'rows' => $monitors->map(fn (NetworkMonitor $monitor) => [
                'name' => $monitor->name,
                'type' => $monitor->type,
                'dependency_type' => $monitor->dependency_type ?: 'external_dependency',
                'source' => $monitor->sourceLabel(),
                'destination' => $monitor->destinationLabel(),
                'protocol' => strtoupper($monitor->protocol ?: $monitor->type),
                'port' => $monitor->target_port,
                'target' => $monitor->endpointLabel(),
                'application' => $monitor->application?->name,
                'last_status' => $monitor->last_status ?? ($monitor->latestResult?->status ?? 'unknown'),
                'last_latency_ms' => $monitor->last_latency_ms ?? $monitor->latestResult?->latency_ms,
                'last_checked_at' => $monitor->last_checked_at?->toDateTimeString() ?? $monitor->latestResult?->checked_at?->toDateTimeString(),
                'last_error' => $monitor->last_error ?? $monitor->latestResult?->error,
            ])->values()->all(),
            'failed_rows' => $failed->take(50)->map(fn (NetworkCheckResult $result) => [
                'name' => $result->networkMonitor?->name,
                'target' => trim($result->target_host.($result->target_port ? ':'.$result->target_port : '')),
                'status' => $result->status,
                'error' => $result->error,
                'checked_at' => $result->checked_at?->toDateTimeString(),
                'affected_application' => $result->networkMonitor?->application?->name,
            ])->values()->all(),
            'dns_mismatches' => $dnsMismatches->take(50)->map(fn (NetworkCheckResult $result) => [
                'name' => $result->networkMonitor?->name,
                'host' => $result->target_host,
                'expected' => $result->expected_value,
                'resolved' => $result->resolved_value,
                'status' => $result->status,
                'affected_application' => $result->networkMonitor?->application?->name,
                'checked_at' => $result->checked_at?->toDateTimeString(),
            ])->values()->all(),
            'port_baseline_violations' => $baselineViolations->map(fn (ServerPortBaseline $baseline) => [
                'server' => $baseline->server?->name,
                'port' => $baseline->port,
                'protocol' => $baseline->protocol,
                'expected_state' => $baseline->expected_state,
                'last_status' => $baseline->last_status,
                'last_error' => $baseline->last_error,
                'last_checked_at' => $baseline->last_checked_at?->toDateTimeString(),
            ])->values()->all(),
        ];
    }

    private function securitySummary(Collection $monitors, CarbonInterface $periodStart, CarbonInterface $periodEnd): array
    {
        $webshell = WebshellScan::query()
            ->whereBetween('scanned_at', [$periodStart, $periodEnd])
            ->get();
        $seo = SeoScan::query()
            ->whereIn('monitor_id', $monitors->pluck('id'))
            ->whereBetween('scanned_at', [$periodStart, $periodEnd])
            ->get();

        return [
            'webshell_scans' => $webshell->count(),
            'webshell_findings' => $webshell->sum(fn (WebshellScan $scan) => count($scan->findings ?? [])),
            'seo_scans' => $seo->count(),
            'seo_suspicious' => $seo->whereIn('status', ['suspicious', 'infected'])->count(),
        ];
    }

    private function incidentSummary(array $website, array $database, array $windowsServices, array $iis, array $network): array
    {
        $rows = [];

        if ($website['downtime_count'] > 0) {
            $rows[] = ['type' => 'Website downtime', 'severity' => 'critical', 'count' => $website['downtime_count']];
        }
        if ($database['failures'] > 0) {
            $rows[] = ['type' => 'Database failure', 'severity' => 'critical', 'count' => $database['failures']];
        }
        if ($windowsServices['stopped_events'] > 0) {
            $rows[] = ['type' => 'Windows service stopped', 'severity' => 'warning', 'count' => $windowsServices['stopped_events']];
        }
        if ($iis['http_500'] > 0) {
            $rows[] = ['type' => 'IIS HTTP 500 errors', 'severity' => 'warning', 'count' => $iis['http_500']];
        }
        if ($iis['suspicious_count'] > 0) {
            $rows[] = ['type' => 'Suspicious IIS events', 'severity' => 'warning', 'count' => $iis['suspicious_count']];
        }
        if (($network['failures'] ?? 0) > 0) {
            $rows[] = ['type' => 'Network connectivity failure', 'severity' => 'critical', 'count' => $network['failures']];
        }

        return ['total' => count($rows), 'rows' => $rows];
    }

    private function recommendations(array $summary): array
    {
        $items = [];

        foreach ($summary['servers']['rows'] as $server) {
            if (($server['disk_max'] ?? 0) >= 85) {
                $items[] = "Review disk capacity on {$server['name']}; peak usage reached {$server['disk_max']}%.";
            }
            if ($server['heartbeat'] !== 'online') {
                $items[] = "Investigate agent heartbeat for {$server['name']}; current state is {$server['heartbeat']}.";
            }
        }

        foreach ($summary['ssl']['rows'] as $certificate) {
            if (in_array($certificate['status'], ['expiring', 'expired'], true)) {
                $items[] = "Renew SSL certificate for {$certificate['url']}; status is {$certificate['status']}.";
            }
        }

        if ($summary['website']['downtime_count'] > 0) {
            $items[] = "Review website downtime events and validate upstream application availability.";
        }
        if ($summary['database']['failures'] > 0) {
            $items[] = "Investigate database check failures and confirm credentials, network path, and database health.";
        }
        if (($summary['network']['failures'] ?? 0) > 0) {
            $items[] = "Investigate failed network dependency checks and verify firewall rules, DNS records, and upstream service health.";
        }
        if (! empty($summary['network']['dns_mismatches'])) {
            $items[] = "Review DNS drift or mismatch events and confirm expected records for affected applications.";
        }
        if (! empty($summary['network']['port_baseline_violations'])) {
            $items[] = "Review server port baseline violations and confirm exposed ports match the approved baseline.";
        }
        if ($summary['windows_services']['stopped_events'] > 0) {
            $items[] = "Review stopped Windows services and confirm recovery actions or maintenance windows.";
        }
        if ($summary['iis']['http_500'] >= 10) {
            $items[] = "Investigate IIS HTTP 500 volume; application exceptions may be impacting users.";
        }
        if ($summary['iis']['suspicious_count'] > 0) {
            $items[] = "Triage suspicious IIS requests and tune allowlists only for verified benign scanners.";
        }

        return array_values(array_unique($items ?: ['Continue routine monitoring. No immediate maintenance action is required for this scope.']));
    }

    private function executiveSummary(array $summary): array
    {
        $criticalSignals = $summary['website']['downtime_count']
            + $summary['database']['failures']
            + ($summary['network']['failures'] ?? 0)
            + $summary['servers']['offline']
            + $summary['ssl']['expired'];

        $warningSignals = $summary['windows_services']['stopped_events']
            + $summary['iis']['http_500']
            + $summary['iis']['suspicious_count']
            + $summary['ssl']['expiring'];

        $status = $criticalSignals > 0 ? 'critical' : ($warningSignals > 0 ? 'warning' : 'healthy');

        return [
            'overall_status' => $status,
            'headline' => match ($status) {
                'critical' => 'Critical maintenance items require attention in the selected reporting period.',
                'warning' => 'The environment is operational with maintenance items to review.',
                default => 'No critical maintenance issues detected for the selected scope.',
            },
            'key_metrics' => [
                'Applications' => $summary['scope']['application_count'],
                'Servers' => $summary['scope']['server_count'],
                'Average Website Uptime' => $summary['website']['average_uptime'] === null ? 'No data' : $summary['website']['average_uptime'].'%',
                'Database Failures' => $summary['database']['failures'],
                'Network Failures' => $summary['network']['failures'] ?? 0,
                'IIS Suspicious Events' => $summary['iis']['suspicious_count'],
            ],
        ];
    }

    private function mergeTopValues(Collection $summaries, string $field): array
    {
        $counts = $summaries
            ->flatMap(fn (IisLogSummary $summary) => $summary->{$field} ?? [])
            ->reduce(function (array $carry, array $item) {
                $value = (string) ($item['value'] ?? $item['url'] ?? $item['ip'] ?? $item['key'] ?? 'unknown');
                $carry[$value] = ($carry[$value] ?? 0) + (int) ($item['count'] ?? 0);

                return $carry;
            }, []);

        arsort($counts);

        return collect($counts)
            ->take(10)
            ->map(fn (int $count, string $value) => ['value' => $value, 'count' => $count])
            ->values()
            ->all();
    }
}
