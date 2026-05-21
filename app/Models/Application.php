<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Application extends Model
{
    public const ROLES = [
        'web',
        'application',
        'database',
        'worker',
        'scheduler',
        'file_storage',
    ];

    public const APPLICATION_TIER_ROLES = [
        'web',
        'application',
        'worker',
        'scheduler',
    ];

    public const DATABASE_TIER_ROLES = [
        'database',
    ];

    public const RULE_APP_SERVERS = 'app_servers';
    public const RULE_DATABASE_SERVERS = 'database_servers';

    protected $fillable = [
        'client_id', 'name', 'code', 'environment', 'owner_team', 'description', 'status', 'architecture_type', 'technology_stack_json',
    ];

    protected $casts = [
        'technology_stack_json' => 'array',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function servers(): BelongsToMany
    {
        return $this->belongsToMany(Server::class, 'application_servers', 'application_id', 'server_id')
            ->using(ApplicationServer::class)
            ->withPivot(['id','role','is_primary','is_required','notes'])
            ->withTimestamps();
    }

    public function urls(): HasMany
    {
        return $this->hasMany(ApplicationUrl::class);
    }

    public function componentRules(): HasMany
    {
        return $this->hasMany(ApplicationComponentRule::class);
    }

    public function networkMonitors(): HasMany
    {
        return $this->hasMany(NetworkMonitor::class);
    }

    /**
     * Compute a simple health summary for the application.
     * Returns: 'critical', 'warning', or 'healthy'.
     */
    public function healthStatus(): string
    {
        return $this->healthSummary()['status'];
    }

    public function healthSummary(): array
    {
        // If any linked URL is explicitly down -> critical
        $urlDown = false;

        foreach ($this->urls as $url) {
            if ($this->urlStatus($url) === 'down') {
                $urlDown = true;
                break;
            }
        }

        $appServers = $this->serversForRoles(self::APPLICATION_TIER_ROLES);
        $databaseServers = $this->serversForRoles(self::DATABASE_TIER_ROLES);
        $requiredOtherServers = $this->servers
            ->filter(fn (Server $server) => ! in_array((string) $server->pivot->role, array_merge(self::APPLICATION_TIER_ROLES, self::DATABASE_TIER_ROLES), true))
            ->filter(fn (Server $server) => (bool) $server->pivot->is_required)
            ->unique('id')
            ->values();

        $healthyAppServers = $appServers->filter(fn (Server $server) => $this->isServerHealthy($server));
        $healthyDatabaseServers = $databaseServers->filter(fn (Server $server) => $this->isServerHealthy($server));
        $healthyRequiredOtherServers = $requiredOtherServers->filter(fn (Server $server) => $this->isServerHealthy($server));

        $minAppServers = $this->minRequired(self::RULE_APP_SERVERS, 'app_server');
        $minDatabaseServers = $this->minRequired(self::RULE_DATABASE_SERVERS, 'db_server');

        $status = 'healthy';
        $reasons = [];

        if ($urlDown) {
            $status = 'critical';
            $reasons[] = 'website_down';
        }

        if ($healthyAppServers->count() < $minAppServers) {
            $status = 'critical';
            $reasons[] = 'app_servers_below_minimum';
        }

        if ($healthyDatabaseServers->count() < $minDatabaseServers) {
            $status = 'critical';
            $reasons[] = 'database_servers_below_minimum';
        }

        if ($healthyRequiredOtherServers->count() < $requiredOtherServers->count()) {
            $status = 'critical';
            $reasons[] = 'required_component_down';
        }

        if ($status !== 'critical') {
            $hasUnhealthyAppNode = $healthyAppServers->count() < $appServers->count();
            $hasUnhealthyDatabaseNode = $healthyDatabaseServers->count() < $databaseServers->count();

            if ($hasUnhealthyAppNode || $hasUnhealthyDatabaseNode) {
                $status = 'warning';
                $reasons[] = 'cluster_node_down';
            }
        }

        return [
            'status' => $status,
            'reasons' => $reasons,
            'url_status' => $this->urlStatusSummary(),
            'app_servers' => [
                'healthy' => $healthyAppServers->count(),
                'total' => $appServers->count(),
                'min_required' => $minAppServers,
            ],
            'database_servers' => [
                'healthy' => $healthyDatabaseServers->count(),
                'total' => $databaseServers->count(),
                'min_required' => $minDatabaseServers,
            ],
            'required_other_components' => [
                'healthy' => $healthyRequiredOtherServers->count(),
                'total' => $requiredOtherServers->count(),
            ],
        ];
    }

    public function minRequired(string $componentType, ?string $legacyComponentType = null): int
    {
        $types = array_filter([$componentType, $legacyComponentType]);
        $rule = $this->componentRules
            ->first(fn (ApplicationComponentRule $rule) => in_array($rule->component_type, $types, true));

        return max(0, (int) ($rule?->min_required ?? 1));
    }

    public function serversForRoles(array $roles)
    {
        return $this->servers
            ->filter(fn (Server $server) => in_array((string) $server->pivot->role, $roles, true))
            ->unique('id')
            ->values();
    }

    public function urlStatusSummary(): string
    {
        if ($this->urls->isEmpty()) {
            return 'none';
        }

        $statuses = $this->urls->map(fn (ApplicationUrl $url) => $this->urlStatus($url));

        if ($statuses->contains('down')) {
            return 'down';
        }

        if ($statuses->contains('unknown')) {
            return 'unknown';
        }

        return 'up';
    }

    protected function urlStatus(ApplicationUrl $url): string
    {
        if ($url->monitor?->latestResult) {
            return $url->monitor->latestResult->is_up ? 'up' : 'down';
        }

        return $url->status ?: 'unknown';
    }

    protected function isServerHealthy(Server $server): bool
    {
        // basic checks: server active and not missing latest metric
        if (! $server->is_active || $server->agentHeartbeatStatus() !== 'online') {
            return false;
        }

        $metric = $server->latestMetric;
        if (! $metric) {
            return false;
        }

        $cpuPercent = (float) $metric->cpu;
        $ramPercent = (float) $metric->ram_total > 0
            ? ((float) $metric->ram_used / (float) $metric->ram_total) * 100
            : null;
        $diskPercent = (float) $metric->disk_total > 0
            ? ((float) $metric->disk_used / (float) $metric->disk_total) * 100
            : null;

        // Check CPU/RAM/Disk thresholds
        if ($server->cpu_threshold !== null && $cpuPercent > (float) $server->cpu_threshold) {
            return false;
        }
        if ($server->ram_threshold !== null && $ramPercent !== null && $ramPercent > (float) $server->ram_threshold) {
            return false;
        }
        if ($server->disk_threshold !== null && $diskPercent !== null && $diskPercent > (float) $server->disk_threshold) {
            return false;
        }

        return true;
    }
}
