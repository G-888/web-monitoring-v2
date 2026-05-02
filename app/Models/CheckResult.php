<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CheckResult extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'monitor_id',
        'status_code',
        'response_time',
        'is_up',
        'checked_at',
    ];

    /**
     * Cast attributes to proper types
     */
    protected $casts = [
        'checked_at'   => 'datetime',
        'is_up'        => 'boolean',
        'response_time'=> 'float',
        'status_code'  => 'integer',
    ];

    /**
     * Relationship back to monitor
     */
    public function monitor()
    {
        return $this->belongsTo(Monitor::class);
    }
}