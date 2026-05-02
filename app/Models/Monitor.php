<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\CheckResult;
use App\Models\User;

class Monitor extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'url',
        'interval',
        'is_active',
        'seo_baseline',
        'seo_enabled',
        'alert_emails',
        'ssl_expires_at',
        'ssl_issuer',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'seo_enabled' => 'boolean',
        'alert_emails' => 'array',
        'ssl_expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get latest check result (used for status display)
     */
    public function latestResult()
    {
        return $this->hasOne(CheckResult::class)->latestOfMany('checked_at');
    }

    public function latestSeoResult()
    {
        return $this->hasOne(SeoResult::class)->latestOfMany('checked_at');
    }

    /**
     * Get all check results
     */
    public function checkResults()
    {
        return $this->hasMany(CheckResult::class);
    }

    public function seoResults()
    {
        return $this->hasMany(SeoResult::class);
    }

    /**
     * Calculate uptime percentage for given hours (default 24h)
     */
    public function uptimePercentage($hours = 24)
    {
        $results = $this->checkResults()
            ->where('checked_at', '>=', now()->subHours($hours));

        $total = $results->count();

        if ($total === 0) {
            return 0;
        }

        $up = (clone $results)
            ->where('is_up', true)
            ->count();

        return round(($up / $total) * 100, 2);
    }
}
