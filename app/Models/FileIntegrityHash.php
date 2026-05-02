<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileIntegrityHash extends Model
{
    protected $fillable = [
        'monitor_id',
        'file_path',
        'hash',
        'last_checked_at'
    ];

    protected $casts = [
        'last_checked_at' => 'datetime'
    ];

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }
}
