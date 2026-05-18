<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DatabaseMonitor extends Model
{
    protected $fillable = [
        'name',
        'driver',
        'host',
        'port',
        'database_name',
        'username',
        'password',
        'is_active',
        'last_status',
        'last_response_time_ms',
        'last_error',
        'last_checked_at',
        'last_failure_alert_at',
        'alert_cooldown_seconds',
    ];

    protected $casts = [
        'password' => 'encrypted',
        'is_active' => 'boolean',
        'last_checked_at' => 'datetime',
        'last_failure_alert_at' => 'datetime',
        'last_response_time_ms' => 'integer',
        'alert_cooldown_seconds' => 'integer',
    ];

    public function checks(): HasMany
    {
        return $this->hasMany(DatabaseCheck::class);
    }

    public function latestCheck(): HasOne
    {
        return $this->hasOne(DatabaseCheck::class)->latestOfMany('checked_at');
    }
}
