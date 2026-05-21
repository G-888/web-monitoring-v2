<?php

use App\Models\NetworkCheckResult;
use App\Models\NetworkMonitor;
use App\Models\Application;
use App\Models\MaintenanceReport;
use App\Models\Server;
use App\Models\ServerPortBaseline;
use App\Models\User;
use App\Services\NetworkAlertService;
use App\Services\NetworkCheckService;
use Spatie\Permission\Models\Permission;

function networkServer(array $overrides = []): Server
{
    return Server::create(array_merge([
        'server_id' => 'net-node-01',
        'name' => 'Network Node 01',
        'ip_address' => '127.0.0.1',
        'is_active' => true,
        'alerts_enabled' => true,
        'offline_threshold_seconds' => 120,
        'alert_cooldown_seconds' => 900,
    ], $overrides));
}

test('tcp port check stores successful result history', function () {
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    expect($socket)->not->toBeFalse($errstr ?: 'Unable to create local TCP test socket.');

    $address = stream_socket_get_name($socket, false);
    $port = (int) substr(strrchr($address, ':'), 1);

    $monitor = NetworkMonitor::create([
        'name' => 'Local TCP',
        'type' => 'tcp_port',
        'source_type' => 'central',
        'target_host' => '127.0.0.1',
        'target_port' => $port,
        'expected_state' => 'open',
        'timeout_ms' => 1000,
        'interval_seconds' => 300,
        'alert_cooldown_seconds' => 900,
        'is_active' => true,
    ]);

    $result = app(NetworkCheckService::class)->checkMonitor($monitor);
    fclose($socket);

    expect($result->is_successful)->toBeTrue()
        ->and($result->status)->toBe('up')
        ->and(NetworkCheckResult::count())->toBe(1)
        ->and($monitor->fresh()->last_status)->toBe('up');
});

test('dns mismatch is recorded when resolved value differs from expected value', function () {
    $monitor = NetworkMonitor::create([
        'name' => 'Localhost DNS',
        'type' => 'dns',
        'source_type' => 'central',
        'target_host' => 'localhost',
        'dns_record_type' => 'A',
        'expected_value' => '203.0.113.10',
        'expected_state' => 'open',
        'timeout_ms' => 1000,
        'interval_seconds' => 300,
        'alert_cooldown_seconds' => 900,
        'is_active' => true,
    ]);

    $result = app(NetworkCheckService::class)->checkMonitor($monitor);

    expect($result->is_successful)->toBeFalse()
        ->and($result->status)->toBe('mismatch')
        ->and($result->resolved_value)->toContain('127.0.0.1')
        ->and($monitor->fresh()->last_status)->toBe('mismatch');
});

test('agent network result endpoint stores results with existing agent authentication', function () {
    config(['services.agent.key' => 'legacy-agent-key']);

    $server = networkServer();
    $monitor = NetworkMonitor::create([
        'name' => 'Agent DB TCP',
        'type' => 'tcp_port',
        'source_type' => 'agent',
        'source_server_id' => $server->id,
        'target_host' => '10.0.0.10',
        'target_port' => 3306,
        'expected_state' => 'open',
        'timeout_ms' => 1000,
        'interval_seconds' => 300,
        'alert_cooldown_seconds' => 900,
        'is_active' => true,
    ]);

    $this->postJson('/api/network-checks/results', [
        'server_id' => $server->server_id,
        'results' => [[
            'monitor_id' => $monitor->id,
            'status' => 'down',
            'is_successful' => false,
            'latency_ms' => 1000,
            'resolved_value' => 'closed',
            'expected_value' => 'open',
            'error' => 'Connection timed out.',
            'checked_at' => now()->toISOString(),
        ]],
    ], ['X-API-Key' => 'legacy-agent-key'])->assertAccepted();

    $result = NetworkCheckResult::first();

    expect($result)->not->toBeNull()
        ->and($result->network_monitor_id)->toBe($monitor->id)
        ->and($result->source_server_id)->toBe($server->id)
        ->and($result->source_type)->toBe('agent')
        ->and($monitor->fresh()->last_status)->toBe('down');
});

test('network monitor dashboard renders for authorized users', function () {
    Permission::firstOrCreate(['name' => 'module.network_monitoring']);
    $user = User::factory()->create();
    $user->givePermissionTo('module.network_monitoring');
    $this->actingAs($user);

    NetworkMonitor::create([
        'name' => 'DNS Dependency',
        'type' => 'dns',
        'source_type' => 'central',
        'target_host' => 'localhost',
        'dns_record_type' => 'A',
        'expected_value' => '127.0.0.1',
        'expected_state' => 'open',
        'timeout_ms' => 1000,
        'interval_seconds' => 300,
        'alert_cooldown_seconds' => 900,
        'is_active' => true,
        'last_status' => 'up',
        'last_checked_at' => now(),
    ]);

    $this->get(route('network-monitors.index'))
        ->assertOk()
        ->assertSee('DNS Dependency')
        ->assertSee('Network Connectivity');
});

test('network monitor can link to application source and target server', function () {
    $application = Application::create([
        'name' => 'Billing',
        'code' => 'billing',
        'environment' => 'production',
    ]);
    $source = networkServer(['server_id' => 'app-01', 'name' => 'App 01']);
    $target = networkServer(['server_id' => 'db-01', 'name' => 'DB 01']);

    $monitor = NetworkMonitor::create([
        'application_id' => $application->id,
        'source_server_id' => $source->id,
        'target_server_id' => $target->id,
        'dependency_type' => 'app_to_db',
        'name' => 'Billing app to database',
        'type' => 'tcp_port',
        'protocol' => 'tcp',
        'source_type' => 'agent',
        'target_host' => '10.0.0.20',
        'target_port' => 3306,
        'expected_state' => 'open',
        'timeout_ms' => 1000,
        'interval_seconds' => 300,
        'alert_cooldown_seconds' => 900,
        'is_active' => true,
    ]);

    expect($monitor->fresh()->application->name)->toBe('Billing')
        ->and($monitor->fresh()->sourceServer->name)->toBe('App 01')
        ->and($monitor->fresh()->targetServer->name)->toBe('DB 01')
        ->and($monitor->fresh()->dependency_type)->toBe('app_to_db');
});

test('failed network monitor shows affected application', function () {
    Permission::firstOrCreate(['name' => 'module.network_monitoring']);
    $user = User::factory()->create();
    $user->givePermissionTo('module.network_monitoring');
    $this->actingAs($user);

    $application = Application::create([
        'name' => 'Inventory',
        'code' => 'inventory',
        'environment' => 'production',
    ]);

    $monitor = NetworkMonitor::create([
        'application_id' => $application->id,
        'name' => 'Inventory API dependency',
        'type' => 'tcp_port',
        'protocol' => 'tcp',
        'source_type' => 'central',
        'dependency_type' => 'server_to_api',
        'target_host' => 'api.internal',
        'target_port' => 443,
        'expected_state' => 'open',
        'timeout_ms' => 1000,
        'interval_seconds' => 300,
        'alert_cooldown_seconds' => 900,
        'is_active' => true,
        'last_status' => 'down',
        'last_error' => 'Connection timed out.',
        'last_checked_at' => now(),
    ]);

    $this->get(route('network-monitors.index'))
        ->assertOk()
        ->assertSee('Inventory API dependency')
        ->assertSee('Affects Inventory');

    $this->get(route('network-monitors.show', $monitor))
        ->assertOk()
        ->assertSee('Inventory')
        ->assertSee('server to api');
});

test('dns drift records failed result and creates alert state', function () {
    $monitor = NetworkMonitor::create([
        'name' => 'Public DNS drift',
        'type' => 'dns',
        'protocol' => 'dns',
        'source_type' => 'central',
        'dependency_type' => 'dns',
        'target_host' => 'app.example.test',
        'dns_record_type' => 'A',
        'expected_state' => 'open',
        'timeout_ms' => 1000,
        'interval_seconds' => 300,
        'alert_cooldown_seconds' => 900,
        'is_active' => true,
    ]);

    NetworkCheckResult::create([
        'network_monitor_id' => $monitor->id,
        'type' => 'dns',
        'source_type' => 'central',
        'target_host' => 'app.example.test',
        'status' => 'up',
        'is_successful' => true,
        'resolved_value' => '198.51.100.10',
        'checked_at' => now()->subMinute(),
    ]);

    $result = app(NetworkCheckService::class)->recordMonitorResult($monitor, [
        'status' => 'up',
        'is_successful' => true,
        'resolved_value' => '198.51.100.20',
        'checked_at' => now(),
    ]);

    app(NetworkAlertService::class)->evaluateMonitor($monitor->fresh(), $result);

    expect($result->status)->toBe('dns_drift')
        ->and($result->is_successful)->toBeFalse()
        ->and($monitor->fresh()->last_alert_at)->not->toBeNull();
});

test('network monitor maintenance mode suppresses alert but keeps result', function () {
    $monitor = NetworkMonitor::create([
        'name' => 'Maintenance API',
        'type' => 'tcp_port',
        'protocol' => 'tcp',
        'source_type' => 'central',
        'target_host' => '127.0.0.1',
        'target_port' => 65534,
        'expected_state' => 'open',
        'timeout_ms' => 1000,
        'interval_seconds' => 300,
        'alert_cooldown_seconds' => 900,
        'maintenance_starts_at' => now()->subMinute(),
        'maintenance_ends_at' => now()->addHour(),
        'is_active' => true,
    ]);

    $result = app(NetworkCheckService::class)->recordMonitorResult($monitor, [
        'status' => 'down',
        'is_successful' => false,
        'resolved_value' => 'closed',
        'error' => 'Connection refused.',
        'checked_at' => now(),
    ]);

    app(NetworkAlertService::class)->evaluateMonitor($monitor->fresh(), $result);

    expect(NetworkCheckResult::count())->toBe(1)
        ->and($monitor->fresh()->last_alert_at)->toBeNull();
});

test('maintenance report includes network dependency section details', function () {
    foreach (['module.reports.view', 'module.reports.generate', 'module.reports.download'] as $permission) {
        Permission::firstOrCreate(['name' => $permission]);
    }

    $user = User::factory()->create();
    $user->givePermissionTo(['module.reports.view', 'module.reports.generate', 'module.reports.download']);
    $this->actingAs($user);

    $application = Application::create([
        'name' => 'Payments',
        'code' => 'payments',
        'environment' => 'production',
    ]);
    $server = networkServer(['server_id' => 'payments-app-01', 'name' => 'Payments App']);
    $monitor = NetworkMonitor::create([
        'application_id' => $application->id,
        'source_server_id' => $server->id,
        'name' => 'Payments DNS',
        'type' => 'dns',
        'protocol' => 'dns',
        'source_type' => 'central',
        'dependency_type' => 'dns',
        'target_host' => 'payments.example.test',
        'dns_record_type' => 'A',
        'expected_value' => '203.0.113.10',
        'expected_state' => 'open',
        'timeout_ms' => 1000,
        'interval_seconds' => 300,
        'alert_cooldown_seconds' => 900,
        'last_status' => 'mismatch',
        'is_active' => true,
    ]);
    NetworkCheckResult::create([
        'network_monitor_id' => $monitor->id,
        'source_server_id' => $server->id,
        'type' => 'dns',
        'source_type' => 'central',
        'target_host' => 'payments.example.test',
        'status' => 'mismatch',
        'is_successful' => false,
        'resolved_value' => '203.0.113.20',
        'expected_value' => '203.0.113.10',
        'error' => 'DNS result does not match expected value.',
        'checked_at' => now(),
    ]);
    ServerPortBaseline::create([
        'server_id' => $server->id,
        'label' => 'MySQL DB',
        'protocol' => 'tcp',
        'port' => 3306,
        'expected_state' => 'open',
        'is_active' => true,
        'last_status' => 'down',
        'last_error' => 'Connection refused.',
        'last_checked_at' => now(),
    ]);

    $this->post(route('reports.maintenance.generate'), [
        'report_type' => 'custom',
        'period_start' => now()->subDay()->toDateString(),
        'period_end' => now()->toDateString(),
        'application_id' => $application->id,
        'output' => 'html',
    ])
        ->assertOk()
        ->assertSee('Network Connectivity Summary')
        ->assertSee('Failed Dependencies')
        ->assertSee('DNS Mismatch / Drift')
        ->assertSee('Port Baseline Violations')
        ->assertSee('Payments');
});
