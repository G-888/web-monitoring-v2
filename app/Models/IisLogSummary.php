<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IisLogSummary extends Model
{
    protected $fillable = [
        'server_id',
        'agent_server_id',
        'window_start',
        'window_end',
        'files_scanned',
        'lines_scanned',
        'total_requests',
        'status_2xx',
        'status_3xx',
        'status_4xx',
        'status_5xx',
        'http_404',
        'http_500',
        'suspicious_count',
        'top_ips',
        'top_urls',
        'parser_errors',
    ];

    protected $casts = [
        'window_start' => 'datetime',
        'window_end' => 'datetime',
        'top_ips' => 'array',
        'top_urls' => 'array',
        'parser_errors' => 'array',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function suspiciousEvents(): HasMany
    {
        return $this->hasMany(IisSuspiciousEvent::class);
    }
}
