<?php

use App\Jobs\ProcessServerMetric;
use App\Models\Server;
use App\Models\ServerMetric;
use App\Models\User;
use App\Models\WindowsService;
use App\Models\WindowsServiceCommand;
use App\Services\ServerAlertService;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;

beforeEach(function () {
    config(['services.agent.key' => 'test-agent-key']);
    $this->withoutMiddleware([
        ValidateCsrfToken::class,
        VerifyCsrfToken::class,
    ]);
});

function metricPayload(array $overrides = []): array
{
    return array_merge([
        'server_id' => 'local-test',
        'cpu' => 12.5,
        'ram_used' => 4.5,
        'ram_total' => 16,
        'disk_used' => 80,
        'disk_total' => 200,
        'timestamp' => now()->subDay()->toISOString(),
    ], $overrides);
}

test('metrics api auto registers unknown servers from valid agents', function () {
    Queue::fake();

    $this->postJson('/api/metrics', metricPayload([
        'agent_hostname' => 'target-web-01',
        'agent_os' => 'Windows Server 2022',
        'agent_version' => '1.2.0',
        'capabilities' => ['systemMetrics', 'windowsServices'],
    ]), [
        'X-API-Key' => 'test-agent-key',
    ])->assertAccepted()
        ->assertJsonPath('status', 'accepted');

    Queue::assertPushed(ProcessServerMetric::class);
    expect(ServerMetric::count())->toBe(0);

    $server = Server::firstWhere('server_id', 'local-test');

    expect($server)
        ->not->toBeNull()
        ->name->toBe('target-web-01')
        ->os->toBe('Windows Server 2022')
        ->agent_version->toBe('1.2.0')
        ->group->toBe('Auto-discovered')
        ->tags->toBe(['agent', 'auto-discovered'])
        ->capabilities->toBe(['systemMetrics', 'windowsServices']);
});

test('metrics api can keep auto registration disabled', function () {
    Queue::fake();
    config(['agent.auto_register_servers' => false]);

    $this->postJson('/api/metrics', metricPayload(), [
        'X-API-Key' => 'test-agent-key',
    ])->assertForbidden();

    Queue::assertNothingPushed();
    expect(Server::where('server_id', 'local-test')->exists())->toBeFalse();
});

test('metrics api accepts registered active servers and queues processing', function () {
    Queue::fake();

    Server::create([
        'server_id' => 'local-test',
        'name' => 'Local Test',
        'is_active' => true,
    ]);

    $this->postJson('/api/metrics', metricPayload(), [
        'X-API-Key' => 'test-agent-key',
    ])->assertAccepted()
        ->assertJsonPath('status', 'accepted');

    Queue::assertPushed(ProcessServerMetric::class);
});

test('metrics api stores reported agent version on the server', function () {
    Queue::fake();

    $server = Server::create([
        'server_id' => 'local-test',
        'name' => 'Local Test',
        'is_active' => true,
    ]);

    $this->postJson('/api/metrics', metricPayload([
        'agent_version' => '1.0.0',
    ]), [
        'X-API-Key' => 'test-agent-key',
    ])->assertAccepted();

    expect($server->refresh()->agent_version)->toBe('1.0.0');
});

test('metric processing uses server receipt time for heartbeat', function () {
    $server = Server::create([
        'server_id' => 'local-test',
        'name' => 'Local Test',
        'is_active' => true,
    ]);

    $job = new ProcessServerMetric(metricPayload([
        'timestamp' => now()->subDays(5)->toISOString(),
    ]));

    $job->handle(app(ServerAlertService::class));

    expect($server->refresh()->last_heartbeat_at)
        ->not->toBeNull()
        ->and($server->last_heartbeat_at->gt(now()->subMinute()))->toBeTrue();
});

test('soft-removed services are updated but stay unmonitored and do not alert', function () {
    $server = Server::create([
        'server_id' => 'local-test',
        'name' => 'Local Test',
        'is_active' => true,
        'alerts_enabled' => true,
    ]);

    $service = WindowsService::create([
        'server_id' => $server->id,
        'service_name' => 'Spooler',
        'display_name' => 'Print Spooler',
        'status' => 'Running',
        'is_monitored' => false,
    ]);

    $alerts = Mockery::mock(ServerAlertService::class);
    $alerts->shouldNotReceive('sendWindowsServiceAlert');

    $job = new ProcessServerMetric(metricPayload([
        'services' => [[
            'name' => 'Spooler',
            'display_name' => 'Print Spooler',
            'status' => 'Stopped',
            'startup_type' => 'Automatic',
        ]],
    ]));

    $job->handle($alerts);

    expect($service->refresh())
        ->status->toBe('Stopped')
        ->is_monitored->toBeFalse();
});

test('service control requires dedicated permission', function () {
    Permission::firstOrCreate(['name' => 'module.server_metrics']);
    Permission::firstOrCreate(['name' => 'module.service_control']);

    $user = User::factory()->create();
    $user->givePermissionTo('module.server_metrics');

    $server = Server::create([
        'server_id' => 'local-test',
        'name' => 'Local Test',
        'is_active' => true,
    ]);

    $service = WindowsService::create([
        'server_id' => $server->id,
        'service_name' => 'Spooler',
        'display_name' => 'Print Spooler',
        'status' => 'Running',
        'is_monitored' => true,
    ]);

    $this->actingAs($user)
        ->post(route('windows-services.commands', $service), ['action' => 'restart'])
        ->assertForbidden();

    expect(WindowsServiceCommand::count())->toBe(0);

    $user->givePermissionTo('module.service_control');

    $this->actingAs($user)
        ->post(route('windows-services.commands', $service), ['action' => 'restart'])
        ->assertRedirect();

    expect(WindowsServiceCommand::first())
        ->service_name->toBe('Spooler')
        ->action->toBe('restart');
});
