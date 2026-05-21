<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerPortBaseline extends Model
{
    protected $fillable = [
        'server_id',
        'label',
        'protocol',
        'port',
        'expected_state',
        'scan_target',
        'timeout_ms',
        'is_active',
        'last_status',
        'last_latency_ms',
        'last_error',
        'last_checked_at',
        'last_alert_at',
        'alert_cooldown_seconds',
    ];

    protected $casts = [
        'port' => 'integer',
        'timeout_ms' => 'integer',
        'is_active' => 'boolean',
        'last_latency_ms' => 'integer',
        'last_checked_at' => 'datetime',
        'last_alert_at' => 'datetime',
        'alert_cooldown_seconds' => 'integer',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
