<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationComponentRule extends Model
{
    protected $fillable = ['application_id','component_type','min_required'];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
