<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatabaseCheck extends Model
{
    protected $fillable = [
        'database_monitor_id',
        'is_up',
        'response_time_ms',
        'error',
        'checked_at',
    ];

    protected $casts = [
        'is_up' => 'boolean',
        'response_time_ms' => 'integer',
        'checked_at' => 'datetime',
    ];

    public function databaseMonitor(): BelongsTo
    {
        return $this->belongsTo(DatabaseMonitor::class);
    }
}
