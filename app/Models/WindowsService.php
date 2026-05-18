<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WindowsService extends Model
{
    protected $fillable = [
        'server_id',
        'service_name',
        'display_name',
        'status',
        'startup_type',
        'is_monitored',
        'last_checked_at',
        'last_alert_at',
    ];

    protected $casts = [
        'is_monitored' => 'boolean',
        'last_checked_at' => 'datetime',
        'last_alert_at' => 'datetime',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function checks(): HasMany
    {
        return $this->hasMany(WindowsServiceCheck::class);
    }

    public function commands(): HasMany
    {
        return $this->hasMany(WindowsServiceCommand::class);
    }
}
