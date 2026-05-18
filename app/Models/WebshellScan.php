<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebshellScan extends Model
{
    protected $fillable = [
        'source',
        'status',
        'target',
        'scanned_files',
        'findings',
        'error',
        'scanned_at',
    ];

    protected $casts = [
        'findings' => 'array',
        'scanned_at' => 'datetime',
    ];
}
