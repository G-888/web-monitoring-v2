<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlertChannel extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'endpoint',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
