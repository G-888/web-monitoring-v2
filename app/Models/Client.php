<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $fillable = [
        'name',
        'code',
        'environment',
        'contact_name',
        'contact_email',
        'support_team',
        'status',
    ];

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}
