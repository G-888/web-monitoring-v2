<?php

use App\Jobs\CheckWebsiteJob;
use App\Models\Monitor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

beforeEach(function () {
    $this->withoutMiddleware([
        ValidateCsrfToken::class,
        VerifyCsrfToken::class,
    ]);
});

test('authenticated users can create monitors', function () {
    Bus::fake();

    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('monitors.store'), [
        'name' => 'Example',
        'url' => 'https://example.com',
        'group' => 'Production',
        'tags' => 'critical, public, critical',
        'interval' => 120,
        'is_active' => '1',
        'seo_enabled' => '1',
    ]);

    $response->assertRedirect(route('dashboard', absolute: false));

    $monitor = Monitor::first();

    expect($monitor)
        ->user_id->toBe($user->id)
        ->name->toBe('Example')
        ->url->toBe('https://example.com')
        ->group->toBe('Production')
        ->tags->toBe(['critical', 'public'])
        ->interval->toBe(120)
        ->is_active->toBeTrue()
        ->seo_enabled->toBeTrue();

    Bus::assertDispatched(CheckWebsiteJob::class);
});

test('authenticated users can update monitors', function () {
    $user = User::factory()->create();
    $monitor = Monitor::create([
        'user_id' => $user->id,
        'name' => 'Old',
        'url' => 'https://old.test',
        'group' => 'Legacy',
        'tags' => ['old'],
        'interval' => 60,
        'is_active' => true,
        'seo_enabled' => true,
    ]);

    $response = $this->actingAs($user)->patch(route('monitors.update', $monitor), [
        'name' => 'New',
        'url' => 'https://new.test',
        'group' => 'QA',
        'tags' => 'internal, staging',
        'interval' => 300,
    ]);

    $response->assertRedirect(route('dashboard', absolute: false));

    expect($monitor->refresh())
        ->name->toBe('New')
        ->url->toBe('https://new.test')
        ->group->toBe('QA')
        ->tags->toBe(['internal', 'staging'])
        ->interval->toBe(300)
        ->is_active->toBeFalse()
        ->seo_enabled->toBeFalse();
});

test('dashboard can filter monitors by group', function () {
    $user = User::factory()->create();

    Monitor::create([
        'user_id' => $user->id,
        'name' => 'Production Monitor',
        'url' => 'https://prod.test',
        'group' => 'Production',
        'tags' => ['critical'],
        'interval' => 60,
        'is_active' => true,
        'seo_enabled' => true,
    ]);

    Monitor::create([
        'user_id' => $user->id,
        'name' => 'Staging Monitor',
        'url' => 'https://staging.test',
        'group' => 'Staging',
        'tags' => ['internal'],
        'interval' => 60,
        'is_active' => true,
        'seo_enabled' => true,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard', ['group' => 'Production']))
        ->assertOk()
        ->assertSee('Production Monitor')
        ->assertSee('critical')
        ->assertDontSee('Staging Monitor');
});

test('authenticated users can pause resume check and delete monitors', function () {
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

    $this->actingAs($user)->post(route('monitors.toggle', $monitor))->assertRedirect();
    expect($monitor->refresh()->is_active)->toBeFalse();

    $this->actingAs($user)->post(route('monitors.check', $monitor))->assertRedirect();
    Bus::assertDispatched(CheckWebsiteJob::class);

    $this->actingAs($user)->delete(route('monitors.destroy', $monitor))->assertRedirect();
    expect(Monitor::find($monitor->id))->toBeNull();
});
