<?php

use App\Jobs\CheckWebsiteJob;
use App\Models\Monitor;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Bus;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutMiddleware([
        ValidateCsrfToken::class,
        VerifyCsrfToken::class,
    ]);
});

test('ssl monitor lists existing https monitors from add monitor flow', function () {
    $user = User::factory()->create();

    Monitor::create([
        'user_id' => $user->id,
        'name' => 'Existing HTTPS',
        'url' => 'https://example.com',
        'interval' => 60,
        'is_active' => true,
        'seo_enabled' => true,
        'ssl_expires_at' => now()->addDays(45),
        'ssl_issuer' => 'Example CA',
        'ssl_last_error' => null,
    ]);

    Monitor::create([
        'user_id' => $user->id,
        'name' => 'Plain HTTP',
        'url' => 'http://example.net',
        'interval' => 60,
        'is_active' => true,
        'seo_enabled' => true,
    ]);

    $this->actingAs($user)
        ->get(route('ssl-monitors.index'))
        ->assertOk()
        ->assertSee('Existing HTTPS')
        ->assertSee('Example CA')
        ->assertSee('Certificate captured')
        ->assertDontSee('https://eams.caam.gov.my/')
        ->assertDontSee('Plain HTTP');
});

test('ssl monitor displays pending failure reason when available', function () {
    $user = User::factory()->create();

    Monitor::create([
        'user_id' => $user->id,
        'name' => 'Pending HTTPS',
        'url' => 'https://pending.example.com',
        'interval' => 60,
        'is_active' => true,
        'seo_enabled' => true,
        'ssl_last_error' => 'Unable to open SSL socket.',
    ]);

    $this->actingAs($user)
        ->get(route('ssl-monitors.index'))
        ->assertOk()
        ->assertSee('Pending HTTPS')
        ->assertSee('Unable to open SSL socket.');
});

test('ssl monitor can add multiple urls and queues checks without duplicates', function () {
    Bus::fake();

    $user = User::factory()->create();

    Monitor::create([
        'user_id' => $user->id,
        'name' => 'EAMS',
        'url' => 'https://eams.caam.gov.my',
        'interval' => 60,
        'is_active' => true,
        'seo_enabled' => true,
    ]);

    $response = $this->actingAs($user)->post(route('ssl-monitors.store'), [
        'urls' => "https://eams.caam.gov.my/\nhttps://asetstor.cidb.gov.my/\nhttp://not-ssl.test",
    ]);

    $response->assertRedirect(route('ssl-monitors.index', absolute: false));

    expect(Monitor::where('url', 'https://eams.caam.gov.my')->count())->toBe(1);
    expect(Monitor::where('url', 'https://asetstor.cidb.gov.my')->exists())->toBeTrue();
    expect(Monitor::where('url', 'http://not-ssl.test')->exists())->toBeFalse();

    Bus::assertDispatchedTimes(CheckWebsiteJob::class, 2);
});

test('ssl monitor check now queues an immediate check', function () {
    Bus::fake();

    $user = User::factory()->create();
    $monitor = Monitor::create([
        'user_id' => $user->id,
        'name' => 'Example',
        'url' => 'https://example.com',
        'interval' => 60,
        'is_active' => true,
        'seo_enabled' => true,
    ]);

    $this->actingAs($user)
        ->post(route('ssl-monitors.check', $monitor))
        ->assertRedirect(route('ssl-monitors.index', absolute: false));

    Bus::assertDispatched(CheckWebsiteJob::class);
});

test('ssl monitor check all queues checks for visible https monitors only', function () {
    Bus::fake();

    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    Monitor::create([
        'user_id' => $user->id,
        'name' => 'First HTTPS',
        'url' => 'https://first.example.com',
        'interval' => 60,
        'is_active' => true,
        'seo_enabled' => true,
    ]);

    Monitor::create([
        'user_id' => $user->id,
        'name' => 'Second HTTPS',
        'url' => 'https://second.example.com',
        'interval' => 60,
        'is_active' => true,
        'seo_enabled' => true,
    ]);

    Monitor::create([
        'user_id' => $user->id,
        'name' => 'HTTP Only',
        'url' => 'http://plain.example.com',
        'interval' => 60,
        'is_active' => true,
        'seo_enabled' => true,
    ]);

    Monitor::create([
        'user_id' => $otherUser->id,
        'name' => 'Other HTTPS',
        'url' => 'https://other.example.com',
        'interval' => 60,
        'is_active' => true,
        'seo_enabled' => true,
    ]);

    $this->actingAs($user)
        ->post(route('ssl-monitors.check-all'))
        ->assertRedirect(route('ssl-monitors.index', absolute: false));

    Bus::assertDispatchedTimes(CheckWebsiteJob::class, 2);
});

test('ssl monitor owner can remove their own https url', function () {
    $user = User::factory()->create();
    $monitor = Monitor::create([
        'user_id' => $user->id,
        'name' => 'Owned HTTPS',
        'url' => 'https://owned.example.com',
        'interval' => 60,
        'is_active' => true,
        'seo_enabled' => true,
    ]);

    $this->actingAs($user)
        ->delete(route('ssl-monitors.destroy', $monitor))
        ->assertRedirect(route('ssl-monitors.index', absolute: false));

    expect(Monitor::find($monitor->id))->toBeNull();
});

test('ssl monitor owner can update alert threshold', function () {
    $user = User::factory()->create();
    $monitor = Monitor::create([
        'user_id' => $user->id,
        'name' => 'Threshold HTTPS',
        'url' => 'https://threshold.example.com',
        'interval' => 60,
        'is_active' => true,
        'seo_enabled' => true,
        'ssl_alert_threshold_days' => 60,
    ]);

    $this->actingAs($user)
        ->patch(route('ssl-monitors.threshold', $monitor), [
            'ssl_alert_threshold_days' => 21,
        ])
        ->assertRedirect(route('ssl-monitors.index', absolute: false));

    expect($monitor->refresh()->ssl_alert_threshold_days)->toBe(21);
});

test('ssl monitor threshold cannot be updated by another user', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $monitor = Monitor::create([
        'user_id' => $owner->id,
        'name' => 'Protected Threshold',
        'url' => 'https://protected-threshold.example.com',
        'interval' => 60,
        'is_active' => true,
        'seo_enabled' => true,
        'ssl_alert_threshold_days' => 60,
    ]);

    $this->actingAs($otherUser)
        ->patch(route('ssl-monitors.threshold', $monitor), [
            'ssl_alert_threshold_days' => 7,
        ])
        ->assertForbidden();

    expect($monitor->refresh()->ssl_alert_threshold_days)->toBe(60);
});

test('ssl monitor does not allow removing another users url unless super admin', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $monitor = Monitor::create([
        'user_id' => $owner->id,
        'name' => 'Owned By Someone Else',
        'url' => 'https://someone-else.example.com',
        'interval' => 60,
        'is_active' => true,
        'seo_enabled' => true,
    ]);

    $this->actingAs($otherUser)
        ->delete(route('ssl-monitors.destroy', $monitor))
        ->assertForbidden();

    expect(Monitor::find($monitor->id))->not->toBeNull();
});

test('ssl monitor super admin can remove any https url', function () {
    Role::findOrCreate('Super Admin');

    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');

    $monitor = Monitor::create([
        'user_id' => $owner->id,
        'name' => 'Admin Removable',
        'url' => 'https://admin-removable.example.com',
        'interval' => 60,
        'is_active' => true,
        'seo_enabled' => true,
    ]);

    $this->actingAs($admin)
        ->delete(route('ssl-monitors.destroy', $monitor))
        ->assertRedirect(route('ssl-monitors.index', absolute: false));

    expect(Monitor::find($monitor->id))->toBeNull();
});
