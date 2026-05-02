<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Run monitoring every minute
Schedule::command('app:run-monitor-checks')->everyMinute();

// SEO Poisoning Detection
Schedule::command('app:run-seo-checks')->hourly();
Schedule::command('app:run-internal-crawl')->daily();

// File Integrity Monitoring
Schedule::command('app:run-file-integrity-checks')->everyTenMinutes();

// SSL renewal reminders daily
Schedule::job(new \App\Jobs\SslRenewalReminderJob)->daily();

// Default Laravel example command (you can keep or remove)
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();