<?php

use App\Models\CheckResult;
use App\Models\Monitor;
use App\Models\User;
use App\Models\WebshellScan;

test('incident history shows website ssl and webshell incidents', function () {
    $user = User::factory()->create();

    $monitor = Monitor::create([
        'user_id' => $user->id,
        'name' => 'Incident Site',
        'url' => 'https://incident.example.com',
        'interval' => 60,
        'is_active' => true,
        'seo_enabled' => true,
        'ssl_expires_at' => now()->subDay(),
        'ssl_alert_threshold_days' => 60,
    ]);

    CheckResult::create([
        'monitor_id' => $monitor->id,
        'status_code' => 500,
        'response_time' => 1.25,
        'is_up' => false,
        'checked_at' => now(),
    ]);

    WebshellScan::create([
        'source' => 'scheduled',
        'status' => 'suspicious',
        'target' => storage_path('app/testing-webshell'),
        'scanned_files' => 2,
        'findings' => [['severity' => 'critical']],
        'scanned_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('incidents.index'))
        ->assertOk()
        ->assertSee('Incident History')
        ->assertSee('Website check failed')
        ->assertSee('SSL certificate expired')
        ->assertSee('Suspicious webshell signatures found');
});

test('incident history scopes website incidents to the current user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $ownMonitor = Monitor::create([
        'user_id' => $user->id,
        'name' => 'Own Down Site',
        'url' => 'https://own-down.example.com',
        'interval' => 60,
        'is_active' => true,
        'seo_enabled' => true,
    ]);

    $otherMonitor = Monitor::create([
        'user_id' => $otherUser->id,
        'name' => 'Other Down Site',
        'url' => 'https://other-down.example.com',
        'interval' => 60,
        'is_active' => true,
        'seo_enabled' => true,
    ]);

    foreach ([$ownMonitor, $otherMonitor] as $monitor) {
        CheckResult::create([
            'monitor_id' => $monitor->id,
            'status_code' => null,
            'response_time' => 0.5,
            'is_up' => false,
            'checked_at' => now(),
        ]);
    }

    $this->actingAs($user)
        ->get(route('incidents.index'))
        ->assertOk()
        ->assertSee('Own Down Site')
        ->assertDontSee('Other Down Site');
});
