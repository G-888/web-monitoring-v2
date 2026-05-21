<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class NetworkMonitor extends Model
{
    public const TYPE_TCP_PORT = 'tcp_port';
    public const TYPE_PING = 'ping';
    public const TYPE_DNS = 'dns';

    public const SOURCE_CENTRAL = 'central';
    public const SOURCE_AGENT = 'agent';

    public const DEPENDENCY_TYPES = [
        'app_to_db',
        'app_to_router',
        'public_to_waf',
        'server_to_svn',
        'server_to_api',
        'dns',
        'external_dependency',
    ];

    protected $fillable = [
        'application_id',
        'source_server_id',
        'target_server_id',
        'name',
        'type',
        'protocol',
        'source_type',
        'dependency_type',
        'target_host',
        'target_port',
        'dns_record_type',
        'expected_value',
        'expected_state',
        'timeout_ms',
        'latency_threshold_ms',
        'interval_seconds',
        'is_active',
        'last_status',
        'last_latency_ms',
        'last_resolved_value',
        'last_error',
        'last_checked_at',
        'last_alert_at',
        'maintenance_starts_at',
        'maintenance_ends_at',
        'alert_cooldown_seconds',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'target_port' => 'integer',
        'timeout_ms' => 'integer',
        'latency_threshold_ms' => 'integer',
        'interval_seconds' => 'integer',
        'last_latency_ms' => 'integer',
        'last_checked_at' => 'datetime',
        'last_alert_at' => 'datetime',
        'maintenance_starts_at' => 'datetime',
        'maintenance_ends_at' => 'datetime',
        'alert_cooldown_seconds' => 'integer',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function sourceServer(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'source_server_id');
    }

    public function targetServer(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'target_server_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(NetworkCheckResult::class);
    }

    public function latestResult(): HasOne
    {
        return $this->hasOne(NetworkCheckResult::class)->latestOfMany('checked_at');
    }

    public function endpointLabel(): string
    {
        if ($this->type === self::TYPE_TCP_PORT && $this->target_port) {
            return "{$this->target_host}:{$this->target_port}";
        }

        return $this->target_host;
    }

    public function sourceLabel(): string
    {
        if ($this->sourceServer) {
            return $this->sourceServer->name;
        }

        return $this->source_type === self::SOURCE_AGENT ? 'Agent source' : 'Central monitor';
    }

    public function destinationLabel(): string
    {
        if ($this->targetServer) {
            return $this->targetServer->name;
        }

        return $this->endpointLabel();
    }

    public function healthBadge(): string
    {
        return match ($this->last_status) {
            'up' => 'healthy',
            'unsupported' => 'warning',
            'down', 'mismatch', 'dns_drift', 'unexpected_open', 'error' => 'critical',
            default => 'unknown',
        };
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
}
