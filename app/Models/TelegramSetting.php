<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramSetting extends Model
{
    protected $fillable = [
        'bot_token',
        'chat_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function setBotTokenAttribute($value)
    {
        $this->attributes['bot_token'] = $value ? encrypt($value) : null;
    }

    public function getBotTokenAttribute($value)
    {
        return $value ? decrypt($value) : null;
    }
}
