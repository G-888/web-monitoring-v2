<?php

use App\Jobs\CheckWebsiteJob;
use App\Jobs\SslRenewalReminderJob;
use App\Mail\MonitorDown;
use App\Mail\SslCertificateExpiring;
use App\Models\CheckResult;
use App\Models\Monitor;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

test('monitor alert recipients prefer configured valid emails', function () {
    $user = User::factory()->create(['email' => 'owner@example.com']);

    $monitor = Monitor::create([
        'user_id' => $user->id,
        'name' => 'Example',
        'url' => 'http://example.test',
        'interval' => 60,
        'is_active' => true,
        'alert_emails' => ['alerts@example.com', 'bad-value', 'alerts@example.com'],
    ]);

    expect($monitor->alertEmailRecipients())->toBe(['alerts@example.com']);
});

test('monitor alert recipients fall back to owner email', function () {
    $user = User::factory()->create(['email' => 'owner@example.com']);

    $monitor = Monitor::create([
        'user_id' => $user->id,
        'name' => 'Example',
        'url' => 'http://example.test',
        'interval' => 60,
        'is_active' => true,
        'alert_emails' => [],
    ]);

    expect($monitor->alertEmailRecipients())->toBe(['owner@example.com']);
});

test('website down alerts use owner fallback without hardcoded recipients', function () {
    Mail::fake();
    Http::fake([
        '*' => Http::response('', 500),
    ]);

    $user = User::factory()->create(['email' => 'owner@example.com']);

    $monitor = Monitor::create([
        'user_id' => $user->id,
        'name' => 'Example',
        'url' => 'http://example.test',
        'interval' => 60,
        'is_active' => true,
        'alert_emails' => [],
    ]);

    (new CheckWebsiteJob($monitor, true))->handle();

    Mail::assertQueued(MonitorDown::class, fn ($mail) => $mail->hasTo('owner@example.com'));
    Mail::assertNotQueued(MonitorDown::class, fn ($mail) => $mail->hasTo('suhailmajemi@gmail.com'));
});

test('website check records failure when request throws and seo is enabled', function () {
    Mail::fake();
    Http::fake([
        '*' => fn () => throw new RuntimeException('Connection refused'),
    ]);

    $user = User::factory()->create(['email' => 'owner@example.com']);

    $monitor = Monitor::create([
        'user_id' => $user->id,
        'name' => 'Failing SEO Monitor',
        'url' => 'http://failing-seo.test',
        'interval' => 60,
        'is_active' => true,
        'seo_enabled' => true,
        'alert_emails' => [],
    ]);

    (new CheckWebsiteJob($monitor, true))->handle();

    $result = CheckResult::where('monitor_id', $monitor->id)->first();

    expect($result)->not->toBeNull()
        ->and($result->is_up)->toBeFalse()
        ->and($result->status_code)->toBeNull();
});

test('ssl reminders use owner fallback without hardcoded recipients', function () {
    Mail::fake();

    $user = User::factory()->create(['email' => 'owner@example.com']);

    Monitor::create([
        'user_id' => $user->id,
        'name' => 'Example',
        'url' => 'https://example.test',
        'interval' => 60,
        'is_active' => true,
        'alert_emails' => [],
        'ssl_expires_at' => now()->addDays(10),
    ]);

    (new SslRenewalReminderJob())->handle();

    Mail::assertSent(SslCertificateExpiring::class, fn ($mail) => $mail->hasTo('owner@example.com'));
    Mail::assertNotSent(SslCertificateExpiring::class, fn ($mail) => $mail->hasTo('suhailmajemi@gmail.com'));
});

test('ssl reminders respect per monitor alert threshold', function () {
    Mail::fake();

    $user = User::factory()->create(['email' => 'owner@example.com']);

    Monitor::create([
        'user_id' => $user->id,
        'name' => 'Outside Threshold',
        'url' => 'https://outside-threshold.test',
        'interval' => 60,
        'is_active' => true,
        'seo_enabled' => true,
        'alert_emails' => [],
        'ssl_expires_at' => now()->addDays(25),
        'ssl_alert_threshold_days' => 14,
    ]);

    Monitor::create([
        'user_id' => $user->id,
        'name' => 'Inside Threshold',
        'url' => 'https://inside-threshold.test',
        'interval' => 60,
        'is_active' => true,
        'seo_enabled' => true,
        'alert_emails' => [],
        'ssl_expires_at' => now()->addDays(10),
        'ssl_alert_threshold_days' => 14,
    ]);

    (new SslRenewalReminderJob())->handle();

    Mail::assertSent(SslCertificateExpiring::class, 1);
    Mail::assertSent(SslCertificateExpiring::class, fn ($mail) => $mail->monitor->name === 'Inside Threshold');
    Mail::assertNotSent(SslCertificateExpiring::class, fn ($mail) => $mail->monitor->name === 'Outside Threshold');
});
