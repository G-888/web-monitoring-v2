<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NetworkCheckResult extends Model
{
    protected $fillable = [
        'network_monitor_id',
        'source_server_id',
        'type',
        'source_type',
        'target_host',
        'target_port',
        'status',
        'is_successful',
        'latency_ms',
        'resolved_value',
        'expected_value',
        'error',
        'checked_at',
    ];

    protected $casts = [
        'is_successful' => 'boolean',
        'target_port' => 'integer',
        'latency_ms' => 'integer',
        'checked_at' => 'datetime',
    ];

    public function networkMonitor(): BelongsTo
    {
        return $this->belongsTo(NetworkMonitor::class);
    }

    public function sourceServer(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'source_server_id');
    }
}
