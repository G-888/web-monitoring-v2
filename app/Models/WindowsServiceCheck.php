<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WindowsServiceCheck extends Model
{
    protected $fillable = [
        'windows_service_id',
        'status',
        'startup_type',
        'checked_at',
    ];

    protected $casts = [
        'checked_at' => 'datetime',
    ];

    public function windowsService(): BelongsTo
    {
        return $this->belongsTo(WindowsService::class);
    }
}
