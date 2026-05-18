<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Run monitoring every minute
Schedule::command('app:run-monitor-checks')->everyMinute();

Schedule::call(function () {
    foreach (\App\Models\Monitor::where('is_active', true)->get() as $monitor) {
        \App\Jobs\CheckWebsiteJob::dispatch($monitor);
        \App\Jobs\ScanDnsIntelligenceJob::dispatch($monitor);
    }
})->everyFiveMinutes();

Schedule::call(function () {
    foreach (\App\Models\DatabaseMonitor::where('is_active', true)->get() as $databaseMonitor) {
        \App\Jobs\CheckDatabaseConnection::dispatch($databaseMonitor);
    }
})->everyFiveMinutes();

// SEO Poisoning Detection
Schedule::command('app:run-seo-checks')->hourly();
Schedule::command('app:run-internal-crawl')->daily();

// File Integrity Monitoring
Schedule::command('app:run-file-integrity-checks')->everyTenMinutes();

// SSL renewal reminders daily
Schedule::job(new \App\Jobs\SslRenewalReminderJob)->daily();

// Server heartbeat/offline threshold alerts
Schedule::job(new \App\Jobs\CheckServerHeartbeats)->everyMinute();

// Default Laravel example command (you can keep or remove)
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();
