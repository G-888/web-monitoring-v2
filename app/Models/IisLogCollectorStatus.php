<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IisLogCollectorStatus extends Model
{
    protected $fillable = [
        'server_id',
        'enabled',
        'last_scan_at',
        'files_seen',
        'files_read',
        'lines_read',
        'summaries_sent',
        'last_error',
        'state_file_path',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'last_scan_at' => 'datetime',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
