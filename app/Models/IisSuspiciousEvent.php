<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IisSuspiciousEvent extends Model
{
    protected $fillable = [
        'server_id',
        'iis_log_summary_id',
        'event_timestamp',
        'ip_address',
        'method',
        'url',
        'status_code',
        'matched_pattern',
        'user_agent',
        'raw',
    ];

    protected $casts = [
        'event_timestamp' => 'datetime',
        'status_code' => 'integer',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function summary(): BelongsTo
    {
        return $this->belongsTo(IisLogSummary::class, 'iis_log_summary_id');
    }
}
