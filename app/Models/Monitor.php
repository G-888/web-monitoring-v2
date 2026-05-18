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
        'group',
        'tags',
        'interval',
        'is_active',
        'seo_baseline',
        'seo_enabled',
        'alert_emails',
        'ssl_expires_at',
        'ssl_issuer',
        'ssl_last_error',
        'ssl_alert_threshold_days',
        'maintenance_starts_at',
        'maintenance_ends_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'seo_enabled' => 'boolean',
        'alert_emails' => 'array',
        'tags' => 'array',
        'ssl_expires_at' => 'datetime',
        'ssl_alert_threshold_days' => 'integer',
        'maintenance_starts_at' => 'datetime',
        'maintenance_ends_at' => 'datetime',
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

    public function isUnderMaintenance(): bool
    {
        if (! $this->maintenance_starts_at) {
            return false;
        }

        if (! $this->maintenance_ends_at) {
            return now()->gte($this->maintenance_starts_at);
        }

        return now()->between($this->maintenance_starts_at, $this->maintenance_ends_at);
    }

    public function alertEmailRecipients(): array
    {
        $configuredEmails = collect($this->alert_emails ?? [])
            ->map(fn ($email) => trim((string) $email))
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values();

        if ($configuredEmails->isNotEmpty()) {
            return $configuredEmails->all();
        }

        $ownerEmail = trim((string) $this->user?->email);

        return filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)
            ? [$ownerEmail]
            : [];
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
