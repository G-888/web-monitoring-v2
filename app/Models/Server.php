<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Server extends Model
{
    protected $fillable = [
        'server_id',
        'name',
        'ip_address',
        'os',
        'location',
        'group',
        'tags',
        'latitude',
        'longitude',
        'is_active',
        'alerts_enabled',
        'cpu_threshold',
        'ram_threshold',
        'disk_threshold',
        'offline_threshold_seconds',
        'alert_cooldown_seconds',
        'last_heartbeat_at',
        'maintenance_starts_at',
        'maintenance_ends_at',
        'agent_version',
        'agent_api_key_hash',
        'agent_api_key_rotated_at',
        'config_schema_version',
        'capabilities',
        'agent_hostname',
        'agent_os',
        'agent_runtime',
        'last_agent_error',
        'server_type',
        'last_cpu_alert_at',
        'last_ram_alert_at',
        'last_disk_alert_at',
        'last_offline_alert_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'alerts_enabled' => 'boolean',
        'last_heartbeat_at' => 'datetime',
        'maintenance_starts_at' => 'datetime',
        'maintenance_ends_at' => 'datetime',
        'agent_api_key_rotated_at' => 'datetime',
        'last_cpu_alert_at' => 'datetime',
        'last_ram_alert_at' => 'datetime',
        'last_disk_alert_at' => 'datetime',
        'last_offline_alert_at' => 'datetime',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'tags' => 'array',
        'capabilities' => 'array',
        'last_agent_error' => 'string',
        'server_type' => 'string',
        'cpu_threshold' => 'decimal:2',
        'ram_threshold' => 'decimal:2',
        'disk_threshold' => 'decimal:2',
    ];

    public function latestMetric(): HasOne
    {
        return $this->hasOne(ServerMetric::class, 'server_id', 'server_id')
            ->latestOfMany('timestamp');
    }

    public function isUnderMaintenance(): bool
    {
        if (! $this->maintenance_starts_at) {
            return false;
        }

        if (! $this->maintenance_ends_at) {
            return now()->gte($this->maintenance_starts_at);
        }

        return now()->between($this->maintenance_starts_at, $this->maintenance_ends_at);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(ServerMetric::class, 'server_id', 'server_id');
    }

    public function windowsServices(): HasMany
    {
        return $this->hasMany(WindowsService::class);
    }

    public function windowsServiceCommands(): HasMany
    {
        return $this->hasMany(WindowsServiceCommand::class);
    }

    public function iisLogSummaries(): HasMany
    {
        return $this->hasMany(IisLogSummary::class);
    }

    public function latestIisLogSummary(): HasOne
    {
        return $this->hasOne(IisLogSummary::class)->latestOfMany();
    }

    public function iisSuspiciousEvents(): HasMany
    {
        return $this->hasMany(IisSuspiciousEvent::class);
    }

    public function applications(): BelongsToMany
    {
        return $this->belongsToMany(Application::class, 'application_servers', 'server_id', 'application_id')
            ->using(ApplicationServer::class)
            ->withPivot(['id','role','is_primary','is_required','notes'])
            ->withTimestamps();
    }

    public function agentHeartbeatStatus(): string
    {
        if (! $this->last_heartbeat_at) {
            return 'unknown';
        }

        $threshold = $this->offline_threshold_seconds ?? 15;
        return $this->last_heartbeat_at->gt(now()->subSeconds($threshold)) ? 'online' : 'offline';
    }

    public function agentVersionState(): string
    {
        if (! $this->agent_version) {
            return 'unknown';
        }

        $latest = config('agent.latest_agent_version');
        $min = config('agent.minimum_supported_agent_version');

        if ($min && version_compare($this->agent_version, $min, '<')) {
            return 'unsupported';
        }

        if ($latest && version_compare($this->agent_version, $latest, '<')) {
            return 'outdated';
        }

        return 'current';
    }

    public function agentStatusSummary(): array
    {
        return [
            'heartbeat' => $this->agentHeartbeatStatus(),
            'version_state' => $this->agentVersionState(),
        ];
    }
}
