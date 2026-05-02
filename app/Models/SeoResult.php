<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoResult extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'monitor_id',
        'is_suspicious',
        'detected_patterns',
        'search_findings',
        'search_queries',
        'checked_at',
    ];

    protected $casts = [
        'is_suspicious' => 'boolean',
        'detected_patterns' => 'array',
        'search_findings' => 'array',
        'search_queries' => 'array',
        'checked_at' => 'datetime',
    ];

    public function monitor()
    {
        return $this->belongsTo(Monitor::class);
    }
}
