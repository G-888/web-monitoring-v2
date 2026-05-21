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

// Lightweight network monitoring; only configured targets and baseline ports are checked.
Schedule::command('app:run-network-checks')->everyFiveMinutes();

// SEO Poisoning Detection
Schedule::command('app:run-seo-checks')->hourly();
Schedule::command('app:run-internal-crawl')->daily();

// File Integrity Monitoring
Schedule::command('app:run-file-integrity-checks')->everyTenMinutes();

// Webshell detection for configured local web roots
Schedule::call(function () {
    collect(explode(',', (string) config('services.webshell.allowed_paths')))
        ->map(fn ($path) => trim($path))
        ->filter()
        ->each(fn ($path) => \App\Jobs\RunWebshellScanJob::dispatch($path, 'scheduled'));
})->dailyAt('03:00');

// SSL renewal reminders daily
Schedule::job(new \App\Jobs\SslRenewalReminderJob)->daily();

// Refresh SSL certificate metadata for active HTTPS monitors every day
Schedule::call(function () {
    \App\Models\Monitor::where('is_active', true)
        ->whereRaw('LOWER(url) LIKE ?', ['https://%'])
        ->each(fn (\App\Models\Monitor $monitor) => \App\Jobs\CheckWebsiteJob::dispatch($monitor, true));
})->dailyAt('02:00');

// Server heartbeat/offline threshold alerts
Schedule::job(new \App\Jobs\CheckServerHeartbeats)->everyMinute();

// Default Laravel example command (you can keep or remove)
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();
