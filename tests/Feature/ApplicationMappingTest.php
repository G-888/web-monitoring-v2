<?php

use App\Models\Application;
use App\Models\ApplicationComponentRule;
use App\Models\ApplicationUrl;
use App\Models\Server;
use App\Models\ServerMetric;

function healthyMappedServer(string $serverId, array $overrides = []): Server
{
    $server = Server::create(array_merge([
        'server_id' => $serverId,
        'name' => ucfirst(str_replace('-', ' ', $serverId)),
        'is_active' => true,
        'alerts_enabled' => true,
        'offline_threshold_seconds' => 120,
        'last_heartbeat_at' => now(),
        'cpu_threshold' => 90,
        'ram_threshold' => 90,
        'disk_threshold' => 90,
    ], $overrides));

    ServerMetric::create([
        'server_id' => $server->server_id,
        'cpu' => 25,
        'ram_used' => 4,
        'ram_total' => 16,
        'disk_used' => 40,
        'disk_total' => 200,
        'timestamp' => now(),
    ]);

    return $server;
}

function mappedApplication(array $rules = []): Application
{
    $application = Application::create([
        'name' => 'Client Portal',
        'code' => 'client-portal',
        'environment' => 'production',
        'status' => 'active',
    ]);

    ApplicationComponentRule::create([
        'application_id' => $application->id,
        'component_type' => Application::RULE_APP_SERVERS,
        'min_required' => $rules['app'] ?? 1,
    ]);

    ApplicationComponentRule::create([
        'application_id' => $application->id,
        'component_type' => Application::RULE_DATABASE_SERVERS,
        'min_required' => $rules['database'] ?? 1,
    ]);

    return $application;
}

test('application mapping can store the same server with application and database roles', function () {
    $this->withoutMiddleware();

    $server = healthyMappedServer('single-node-01');

    $this->post(route('applications.store'), [
        'name' => 'Single Node App',
        'code' => 'single-node-app',
        'environment' => 'production',
        'app_servers_min_required' => 1,
        'database_servers_min_required' => 1,
        'mappings' => [
            ['server_id' => $server->id, 'role' => 'application', 'is_primary' => '1', 'is_required' => '1'],
            ['server_id' => $server->id, 'role' => 'database', 'is_primary' => '1', 'is_required' => '1'],
        ],
    ])->assertRedirect();

    $application = Application::with(['servers.latestMetric', 'urls.monitor.latestResult', 'componentRules'])
        ->firstWhere('code', 'single-node-app');

    expect($application->servers)->toHaveCount(2)
        ->and($application->servers->pluck('pivot.role')->all())->toBe(['application', 'database'])
        ->and($application->healthSummary()['status'])->toBe('healthy')
        ->and($application->healthSummary()['app_servers']['healthy'])->toBe(1)
        ->and($application->healthSummary()['database_servers']['healthy'])->toBe(1);
});

test('application health warns when one cluster node is down but minimums are met', function () {
    $application = mappedApplication(['app' => 1, 'database' => 1]);
    $healthyApp = healthyMappedServer('app-node-01');
    $offlineApp = healthyMappedServer('app-node-02', [
        'last_heartbeat_at' => now()->subMinutes(10),
    ]);
    $database = healthyMappedServer('db-node-01');

    $application->servers()->attach($healthyApp->id, ['role' => 'application', 'is_required' => true]);
    $application->servers()->attach($offlineApp->id, ['role' => 'application', 'is_required' => true]);
    $application->servers()->attach($database->id, ['role' => 'database', 'is_required' => true]);

    $summary = $application->fresh()
        ->load(['servers.latestMetric', 'urls.monitor.latestResult', 'componentRules'])
        ->healthSummary();

    expect($summary['status'])->toBe('warning')
        ->and($summary['app_servers']['healthy'])->toBe(1)
        ->and($summary['app_servers']['total'])->toBe(2);
});

test('application health is critical when url is down or minimums are not met', function () {
    $application = mappedApplication(['app' => 2, 'database' => 1]);
    $appServer = healthyMappedServer('only-app-node');
    $database = healthyMappedServer('only-db-node');

    $application->servers()->attach($appServer->id, ['role' => 'application', 'is_required' => true]);
    $application->servers()->attach($database->id, ['role' => 'database', 'is_required' => true]);
    ApplicationUrl::create([
        'application_id' => $application->id,
        'url' => 'https://example.test',
        'status' => 'down',
    ]);

    $summary = $application->fresh()
        ->load(['servers.latestMetric', 'urls.monitor.latestResult', 'componentRules'])
        ->healthSummary();

    expect($summary['status'])->toBe('critical')
        ->and($summary['reasons'])->toContain('website_down')
        ->and($summary['reasons'])->toContain('app_servers_below_minimum');
});
