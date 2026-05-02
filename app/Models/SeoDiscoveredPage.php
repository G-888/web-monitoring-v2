<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoDiscoveredPage extends Model
{
    protected $fillable = [
        'monitor_id',
        'url',
        'hash',
        'last_seen_at',
        'is_baseline'
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'is_baseline' => 'boolean'
    ];

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }
}
