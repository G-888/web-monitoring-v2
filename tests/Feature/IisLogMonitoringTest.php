<?php

use App\Models\IisLogSummary;
use App\Models\IisSuspiciousEvent;
use App\Models\Server;
use App\Models\User;
use App\Services\AgentDeploymentService;
use App\Services\ServerAlertService;
use Spatie\Permission\Models\Permission;

function iisServer(array $overrides = []): Server
{
    return Server::create(array_merge([
        'server_id' => 'iis-node-01',
        'name' => 'IIS Node 01',
        'is_active' => true,
        'alerts_enabled' => true,
        'offline_threshold_seconds' => 120,
        'alert_cooldown_seconds' => 900,
    ], $overrides));
}

test('iis log summary endpoint stores summary and suspicious samples with per server key', function () {
    config(['services.agent.key' => null]);

    $server = iisServer();
    $plainKey = app(AgentDeploymentService::class)->generatePlainKey($server);

    $payload = [
        'server_id' => 'iis-node-01',
        'window_start' => now()->subMinute()->toISOString(),
        'window_end' => now()->toISOString(),
        'files_scanned' => 1,
        'lines_scanned' => 12,
        'total_requests' => 10,
        'status_2xx' => 6,
        'status_3xx' => 1,
        'status_4xx' => 2,
        'status_5xx' => 1,
        'http_404' => 2,
        'http_500' => 1,
        'suspicious_count' => 1,
        'top_ips' => [['value' => '203.0.113.10', 'count' => 4]],
        'top_urls' => [['value' => '/index.cfm', 'count' => 3]],
        'suspicious_samples' => [[
            'timestamp' => now()->toISOString(),
            'ip' => '203.0.113.10',
            'method' => 'GET',
            'url' => '/index.cfm?id=1 union select password',
            'status_code' => 404,
            'matched_pattern' => 'union select',
            'user_agent' => 'sqlmap',
        ]],
    ];

    $this->postJson('/api/iis-logs/summary', $payload, [
        'X-API-Key' => $plainKey,
    ])->assertAccepted();

    expect(IisLogSummary::count())->toBe(1)
        ->and(IisLogSummary::first()->server_id)->toBe($server->id)
        ->and(IisLogSummary::first()->total_requests)->toBe(10)
        ->and(IisSuspiciousEvent::count())->toBe(1)
        ->and(IisSuspiciousEvent::first()->matched_pattern)->toBe('union select');
});

test('iis log summary endpoint keeps existing global agent key compatibility', function () {
    config(['services.agent.key' => 'legacy-agent-key']);

    iisServer();

    $this->postJson('/api/iis-logs/summary', [
        'server_id' => 'iis-node-01',
        'total_requests' => 1,
    ], [
        'X-API-Key' => 'legacy-agent-key',
    ])->assertAccepted();

    expect(IisLogSummary::count())->toBe(1);
});

test('iis log summary endpoint rejects invalid agent keys', function () {
    config(['services.agent.key' => 'legacy-agent-key']);

    iisServer();

    $this->postJson('/api/iis-logs/summary', [
        'server_id' => 'iis-node-01',
        'total_requests' => 1,
    ], [
        'X-API-Key' => 'wrong-key',
    ])->assertUnauthorized();

    expect(IisLogSummary::count())->toBe(0);
});

test('iis log alerts fire once per cooldown window', function () {
    config([
        'services.agent.key' => 'legacy-agent-key',
        'agent.iis_logs.alerts.http_500_threshold' => 2,
        'agent.iis_logs.alerts.http_404_threshold' => 100,
        'agent.iis_logs.alerts.suspicious_threshold' => 100,
        'agent.iis_logs.alerts.cooldown_seconds' => 900,
    ]);

    iisServer();

    $alerts = Mockery::mock(ServerAlertService::class);
    $alerts->shouldReceive('sendIisLogAlert')->once();
    app()->instance(ServerAlertService::class, $alerts);

    $payload = [
        'server_id' => 'iis-node-01',
        'total_requests' => 10,
        'http_500' => 3,
    ];

    $this->postJson('/api/iis-logs/summary', $payload, ['X-API-Key' => 'legacy-agent-key'])->assertAccepted();
    $this->postJson('/api/iis-logs/summary', $payload, ['X-API-Key' => 'legacy-agent-key'])->assertAccepted();
});

test('iis log dashboard and server detail render stored summaries', function () {
    Permission::firstOrCreate(['name' => 'module.log_ingestion']);
    $user = User::factory()->create();
    $user->givePermissionTo('module.log_ingestion');
    $this->actingAs($user);

    $server = iisServer();
    $summary = IisLogSummary::create([
        'server_id' => $server->id,
        'agent_server_id' => $server->server_id,
        'window_start' => now()->subMinute(),
        'window_end' => now(),
        'total_requests' => 25,
        'http_404' => 4,
        'http_500' => 1,
        'suspicious_count' => 2,
        'top_ips' => [['value' => '203.0.113.10', 'count' => 10]],
        'top_urls' => [['value' => '/login', 'count' => 8]],
    ]);
    IisSuspiciousEvent::create([
        'server_id' => $server->id,
        'iis_log_summary_id' => $summary->id,
        'event_timestamp' => now(),
        'ip_address' => '203.0.113.10',
        'url' => '/login?x=../../web.config',
        'status_code' => 404,
        'matched_pattern' => '../',
        'user_agent' => 'nikto',
    ]);

    $this->get(route('iis-logs.index'))
        ->assertOk()
        ->assertSee('IIS Node 01')
        ->assertSee('25');

    $this->get(route('iis-logs.show', $server))
        ->assertOk()
        ->assertSee('203.0.113.10')
        ->assertSee('/login')
        ->assertSee('nikto');
});
