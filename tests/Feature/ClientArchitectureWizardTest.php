<?php

use App\Models\Application;
use App\Models\AgentDeploymentAudit;
use App\Models\Client;
use App\Models\DatabaseMonitor;
use App\Models\NetworkMonitor;
use App\Models\Server;
use App\Models\User;
use App\Services\ArchitectureOnboardingReviewService;
use App\Services\ArchitectureTemplateService;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->withoutMiddleware();
    $this->actingAs(User::factory()->create());
});

function architectureServer(string $id, string $name): Server
{
    return Server::create([
        'server_id' => $id,
        'name' => $name,
        'ip_address' => '10.10.10.'.random_int(10, 200),
        'is_active' => true,
        'alerts_enabled' => true,
        'offline_threshold_seconds' => 15,
        'alert_cooldown_seconds' => 900,
    ]);
}

function architecturePayload(array $overrides = []): array
{
    return array_replace_recursive([
        'client' => [
            'name' => 'Acme Agency',
            'code' => 'acme-agency',
            'environment' => 'production',
            'support_team' => 'Platform',
            'status' => 'active',
        ],
        'application' => [
            'name' => 'Acme Portal',
            'code' => 'acme-portal',
            'environment' => 'production',
            'public_url' => 'https://portal.example.test',
        ],
        'technology_stack' => ['IIS', 'ColdFusion', 'MySQL'],
        'architecture_type' => ArchitectureTemplateService::SINGLE_APP_DB,
        'role_servers' => [],
    ], $overrides);
}

test('single server template generates app and database roles on the same server', function () {
    $server = architectureServer('single-01', 'Single 01');

    $this->post(route('client-architecture.setup.store'), architecturePayload([
        'role_servers' => [
            'app_database' => [$server->id],
        ],
    ]))->assertRedirect();

    $application = Application::where('code', 'acme-portal')->firstOrFail();
    $roles = $application->servers()->where('servers.id', $server->id)->pluck('application_servers.role')->sort()->values()->all();

    expect($application->client)->not->toBeNull()
        ->and($application->architecture_type)->toBe(ArchitectureTemplateService::SINGLE_APP_DB)
        ->and($roles)->toBe(['application', 'database']);
});

test('two app and three db template generates correct mappings', function () {
    $app1 = architectureServer('app-01', 'App 01');
    $app2 = architectureServer('app-02', 'App 02');
    $db1 = architectureServer('db-01', 'DB 01');
    $db2 = architectureServer('db-02', 'DB 02');
    $db3 = architectureServer('db-03', 'DB 03');

    $this->post(route('client-architecture.setup.store'), architecturePayload([
        'application' => [
            'name' => 'Cluster Portal',
            'code' => 'cluster-portal',
            'environment' => 'production',
        ],
        'architecture_type' => ArchitectureTemplateService::TWO_APP_THREE_DB,
        'role_servers' => [
            'application' => [$app1->id, $app2->id],
            'database' => [$db1->id, $db2->id, $db3->id],
        ],
    ]))->assertRedirect();

    $application = Application::where('code', 'cluster-portal')->firstOrFail();

    expect($application->servers()->wherePivot('role', 'application')->count())->toBe(2)
        ->and($application->servers()->wherePivot('role', 'database')->count())->toBe(3)
        ->and(DatabaseMonitor::where('application_id', $application->id)->count())->toBe(3);
});

test('mysql router template creates localhost router monitors', function () {
    $app = architectureServer('router-app-01', 'Router App 01');
    $db1 = architectureServer('router-db-01', 'Router DB 01');
    $db2 = architectureServer('router-db-02', 'Router DB 02');
    $db3 = architectureServer('router-db-03', 'Router DB 03');

    $this->post(route('client-architecture.setup.store'), architecturePayload([
        'application' => [
            'name' => 'Router Portal',
            'code' => 'router-portal',
            'environment' => 'production',
        ],
        'architecture_type' => ArchitectureTemplateService::APP_ROUTER_DB_CLUSTER,
        'role_servers' => [
            'application' => [$app->id],
            'database' => [$db1->id, $db2->id, $db3->id],
        ],
    ]))->assertRedirect();

    $application = Application::where('code', 'router-portal')->firstOrFail();
    $ports = NetworkMonitor::where('application_id', $application->id)
        ->where('dependency_type', 'app_to_router')
        ->pluck('target_port')
        ->sort()
        ->values()
        ->all();

    expect($ports)->toBe([6446, 6447]);
});

test('bulk package zip includes all mapped server packages', function () {
    $app1 = architectureServer('bulk-app-01', 'Bulk App 01');
    $db1 = architectureServer('bulk-db-01', 'Bulk DB 01');

    $response = $this->post(route('client-architecture.setup.store'), architecturePayload([
        'application' => [
            'name' => 'Bulk Portal',
            'code' => 'bulk-portal',
            'environment' => 'production',
        ],
        'architecture_type' => ArchitectureTemplateService::TWO_APP_ONE_DB,
        'role_servers' => [
            'application' => [$app1->id],
            'database' => [$db1->id],
        ],
        'generate_packages' => '1',
    ]));

    $response->assertOk();
    $zipPath = $response->baseResponse->getFile()->getPathname();
    $zip = new ZipArchive();
    expect($zip->open($zipPath))->toBeTrue()
        ->and($zip->locateName('ServerMonitorAgent-Bulk-App-01-vnew.zip'))->not->toBeFalse()
        ->and($zip->locateName('ServerMonitorAgent-Bulk-DB-01-vnew.zip'))->not->toBeFalse();
    $zip->close();
});

test('client report scope includes correct apps and servers', function () {
    $client = Client::create([
        'name' => 'Report Client',
        'code' => 'report-client',
        'environment' => 'production',
        'status' => 'active',
    ]);
    $otherClient = Client::create([
        'name' => 'Other Client',
        'code' => 'other-client',
        'environment' => 'production',
        'status' => 'active',
    ]);
    $server = architectureServer('report-app-01', 'Report App 01');
    $otherServer = architectureServer('report-other-01', 'Other App 01');

    $application = Application::create([
        'client_id' => $client->id,
        'name' => 'Report App',
        'code' => 'report-app',
        'environment' => 'production',
        'status' => 'active',
    ]);
    $application->servers()->attach($server->id, ['role' => 'application', 'is_required' => true]);

    $otherApplication = Application::create([
        'client_id' => $otherClient->id,
        'name' => 'Other App',
        'code' => 'other-app',
        'environment' => 'production',
        'status' => 'active',
    ]);
    $otherApplication->servers()->attach($otherServer->id, ['role' => 'application', 'is_required' => true]);

    $response = $this->post(route('reports.maintenance.generate'), [
        'report_type' => 'weekly',
        'period_start' => now()->subWeek()->toDateString(),
        'period_end' => now()->toDateString(),
        'client_id' => $client->id,
        'output' => 'html',
    ]);

    $response->assertOk();
    $summary = $response->viewData('summary');

    expect($summary['scope']['client']['id'])->toBe($client->id)
        ->and($summary['scope']['application_count'])->toBe(1)
        ->and($summary['scope']['server_count'])->toBe(1)
        ->and($summary['applications'][0]['name'])->toBe('Report App');
});

test('wizard completion creates architecture review data', function () {
    $server = architectureServer('review-single-01', 'Review Single 01');

    $this->post(route('client-architecture.setup.store'), architecturePayload([
        'application' => [
            'name' => 'Review Portal',
            'code' => 'review-portal',
            'environment' => 'production',
            'public_url' => 'https://review.example.test',
        ],
        'role_servers' => [
            'app_database' => [$server->id],
        ],
    ]))->assertRedirect();

    $application = Application::where('code', 'review-portal')->firstOrFail();
    $review = app(ArchitectureOnboardingReviewService::class)->build($application);

    $this->get(route('applications.architecture-review', $application))
        ->assertOk()
        ->assertSee('Architecture Review')
        ->assertSee('Mapped Servers')
        ->assertSee('Generated Network Monitors')
        ->assertSee('DNS Monitors')
        ->assertSee('Review Portal');

    expect($review['client']->name)->toBe('Acme Agency')
        ->and($review['application']->id)->toBe($application->id)
        ->and($review['architecture_type'])->toBe(ArchitectureTemplateService::SINGLE_APP_DB)
        ->and($review['app_servers'])->toHaveCount(1)
        ->and($review['db_servers'])->toHaveCount(1)
        ->and($review['dns_monitors'])->toHaveCount(1)
        ->and($review['db_monitors'])->toHaveCount(1);
});

test('db monitor placeholder can be configured and enabled', function () {
    $application = Application::create([
        'name' => 'DB Setup App',
        'code' => 'db-setup-app',
        'environment' => 'production',
        'status' => 'active',
    ]);
    $server = architectureServer('db-setup-01', 'DB Setup 01');
    $monitor = DatabaseMonitor::create([
        'application_id' => $application->id,
        'server_id' => $server->id,
        'name' => 'DB Setup 01 MySQL',
        'driver' => 'mysql',
        'host' => $server->ip_address,
        'port' => 3306,
        'database_name' => 'placeholder',
        'username' => 'placeholder',
        'is_active' => false,
    ]);

    $this->get(route('database-monitors.guided-setup', $monitor))
        ->assertOk()
        ->assertSee('DB Monitor Guided Setup')
        ->assertSee('Pending Configuration');

    $this->patch(route('database-monitors.guided-setup.update', $monitor), [
        'driver' => 'mysql',
        'host' => '10.20.30.40',
        'port' => 3306,
        'database_name' => 'webmonitor',
        'username' => 'monitor_user',
        'password' => 'super-secret',
        'default_query' => 'select 1',
        'db_role' => 'primary',
    ])->assertRedirect(route('database-monitors.guided-setup', $monitor));

    $monitor->refresh();

    expect($monitor->configured_at)->not->toBeNull()
        ->and($monitor->db_role)->toBe('primary')
        ->and($monitor->is_active)->toBeFalse();

    $this->post(route('database-monitors.guided-setup.enable', $monitor), [
        'confirm_enable' => '1',
    ])->assertRedirect(route('database-monitors.guided-setup', $monitor));

    $monitor->refresh();

    expect($monitor->is_active)->toBeTrue()
        ->and($monitor->enabled_at)->not->toBeNull();
});

test('encrypted database password is not stored as plaintext', function () {
    $monitor = DatabaseMonitor::create([
        'name' => 'Encrypted Password Monitor',
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => 3306,
        'database_name' => 'placeholder',
        'username' => 'placeholder',
        'is_active' => false,
    ]);

    $this->patch(route('database-monitors.guided-setup.update', $monitor), [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => 3306,
        'database_name' => 'webmonitor',
        'username' => 'monitor_user',
        'password' => 'super-secret',
        'default_query' => 'select 1',
        'db_role' => 'cluster_member',
    ])->assertRedirect(route('database-monitors.guided-setup', $monitor));

    $stored = DB::table('database_monitors')->whereKey($monitor->id)->value('password');

    expect($stored)->not->toBe('super-secret')
        ->and($monitor->refresh()->password)->toBe('super-secret');
});

test('agent deployment status detects no heartbeat as not installed', function () {
    $server = architectureServer('no-heartbeat-01', 'No Heartbeat 01');
    $application = Application::create([
        'name' => 'No Heartbeat App',
        'code' => 'no-heartbeat-app',
        'environment' => 'production',
        'status' => 'active',
    ]);
    $application->servers()->attach($server->id, ['role' => 'application', 'is_required' => true]);

    $review = app(ArchitectureOnboardingReviewService::class)->build($application);
    $status = $review['deployment']->first();

    expect($status['agent_installed'])->toBeFalse()
        ->and($status['heartbeat_status'])->toBe('unknown');
});

test('profile drift detects iis logs enabled on db server', function () {
    $server = architectureServer('db-drift-01', 'DB Drift 01');
    $server->update([
        'capabilities' => ['systemMetrics', 'windowsServices', 'databaseCheck', 'networkChecks', 'iisLogs'],
    ]);
    $application = Application::create([
        'name' => 'DB Drift App',
        'code' => 'db-drift-app',
        'environment' => 'production',
        'status' => 'active',
    ]);
    $application->servers()->attach($server->id, ['role' => 'database', 'is_required' => true]);

    $review = app(ArchitectureOnboardingReviewService::class)->build($application);
    $drift = $review['drift']->first();

    expect($drift['status'])->toBe('Unexpected Module')
        ->and($drift['unexpected_modules'])->toContain('iisLogs');
});

test('onboarding score calculates complete setup', function () {
    $client = Client::create([
        'name' => 'Complete Client',
        'code' => 'complete-client',
        'environment' => 'production',
        'status' => 'active',
    ]);
    $server = architectureServer('complete-single-01', 'Complete Single 01');
    $server->update([
        'last_heartbeat_at' => now(),
        'agent_version' => '1.0.0',
        'capabilities' => ['systemMetrics', 'windowsServices', 'databaseCheck', 'iisLogs', 'networkChecks'],
    ]);
    $application = Application::create([
        'client_id' => $client->id,
        'name' => 'Complete App',
        'code' => 'complete-app',
        'environment' => 'production',
        'status' => 'active',
        'architecture_type' => ArchitectureTemplateService::SINGLE_APP_DB,
    ]);
    $application->servers()->attach($server->id, ['role' => 'application', 'is_required' => true]);
    $application->servers()->attach($server->id, ['role' => 'database', 'is_required' => true]);

    NetworkMonitor::create([
        'application_id' => $application->id,
        'source_server_id' => $server->id,
        'target_server_id' => $server->id,
        'name' => 'Complete App DB',
        'type' => NetworkMonitor::TYPE_TCP_PORT,
        'source_type' => NetworkMonitor::SOURCE_AGENT,
        'dependency_type' => 'app_to_db',
        'target_host' => $server->ip_address,
        'target_port' => 3306,
        'expected_state' => 'open',
        'is_active' => true,
    ]);
    DatabaseMonitor::create([
        'application_id' => $application->id,
        'server_id' => $server->id,
        'db_role' => 'primary',
        'name' => 'Complete DB',
        'driver' => 'mysql',
        'host' => $server->ip_address,
        'port' => 3306,
        'database_name' => 'webmonitor',
        'username' => 'monitor_user',
        'is_active' => true,
        'configured_at' => now(),
    ]);
    AgentDeploymentAudit::create([
        'server_id' => $server->id,
        'user_id' => auth()->id(),
        'action' => 'package_downloaded',
        'metadata' => ['test' => true],
    ]);

    $review = app(ArchitectureOnboardingReviewService::class)->build($application);

    expect($review['score'])->toBe(100)
        ->and(collect($review['checklist'])->every(fn (array $item) => $item['complete']))->toBeTrue();
});
