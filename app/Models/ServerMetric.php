<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServerMetric extends Model
{
    protected $fillable = [
        'server_id',
        'cpu',
        'ram_used',
        'ram_total',
        'disk_used',
        'disk_total',
        'timestamp',
    ];

    protected $casts = [
        'cpu' => 'decimal:2',
        'ram_used' => 'decimal:2',
        'ram_total' => 'decimal:2',
        'disk_used' => 'decimal:2',
        'disk_total' => 'decimal:2',
        'timestamp' => 'datetime',
    ];

    public function scopeLatestForServer($query, $serverId)
    {
        return $query->where('server_id', $serverId)
                    ->orderBy('timestamp', 'desc')
                    ->limit(1);
    }
}
