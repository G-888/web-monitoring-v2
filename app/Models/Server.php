<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        'last_cpu_alert_at' => 'datetime',
        'last_ram_alert_at' => 'datetime',
        'last_disk_alert_at' => 'datetime',
        'last_offline_alert_at' => 'datetime',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'tags' => 'array',
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
}
