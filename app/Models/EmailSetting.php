<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class EmailSetting extends Model
{
    protected $fillable = [
        'mailer',
        'host',
        'port',
        'encryption',
        'username',
        'password',
        'from_address',
        'from_name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'port' => 'integer',
    ];

    // Encrypt password when setting
    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = $value ? Crypt::encryptString($value) : null;
    }

    // Decrypt password when getting
    public function getPasswordAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    // Get the active email settings
    public static function getActive()
    {
        return static::where('is_active', true)->first();
    }

    // Get mail configuration array for Laravel
    public function toMailConfig()
    {
        return [
            'default' => $this->mailer,
            'mailers' => [
                'smtp' => [
                    'transport' => 'smtp',
                    'host' => $this->host,
                    'port' => $this->port,
                    'encryption' => $this->encryption,
                    'username' => $this->username,
                    'password' => $this->password,
                    'timeout' => null,
                ],
            ],
            'from' => [
                'address' => $this->from_address,
                'name' => $this->from_name,
            ],
        ];
    }
}
