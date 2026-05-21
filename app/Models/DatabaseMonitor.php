<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DatabaseMonitor extends Model
{
    protected $fillable = [
        'name',
        'application_id',
        'server_id',
        'db_role',
        'driver',
        'host',
        'port',
        'database_name',
        'username',
        'password',
        'default_query',
        'is_active',
        'last_status',
        'last_response_time_ms',
        'last_error',
        'last_checked_at',
        'configured_at',
        'enabled_at',
        'last_failure_alert_at',
        'alert_cooldown_seconds',
    ];

    protected $casts = [
        'password' => 'encrypted',
        'is_active' => 'boolean',
        'last_checked_at' => 'datetime',
        'configured_at' => 'datetime',
        'enabled_at' => 'datetime',
        'last_failure_alert_at' => 'datetime',
        'last_response_time_ms' => 'integer',
        'alert_cooldown_seconds' => 'integer',
    ];

    public function checks(): HasMany
    {
        return $this->hasMany(DatabaseCheck::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function latestCheck(): HasOne
    {
        return $this->hasOne(DatabaseCheck::class)->latestOfMany('checked_at');
    }
}
