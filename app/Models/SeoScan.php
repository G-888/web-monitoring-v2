<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoScan extends Model
{
    protected $fillable = [
        'monitor_id',
        'url',
        'status',
        'findings',
        'diffs',
        'scanned_at'
    ];

    protected $casts = [
        'findings' => 'array',
        'diffs' => 'array',
        'scanned_at' => 'datetime'
    ];

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }
}
