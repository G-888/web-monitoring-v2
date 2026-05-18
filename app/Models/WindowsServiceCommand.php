<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WindowsServiceCommand extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'server_id',
        'windows_service_id',
        'service_name',
        'action',
        'status',
        'output',
        'error',
        'picked_up_at',
        'completed_at',
    ];

    protected $casts = [
        'picked_up_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function windowsService(): BelongsTo
    {
        return $this->belongsTo(WindowsService::class);
    }
}
