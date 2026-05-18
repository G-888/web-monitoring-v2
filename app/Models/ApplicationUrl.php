<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationUrl extends Model
{
    protected $fillable = ['application_id','monitor_id','url','status'];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }
}
