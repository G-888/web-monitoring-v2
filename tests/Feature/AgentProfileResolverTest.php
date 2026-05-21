<?php

use App\Models\AgentDeploymentAudit;
use App\Models\Application;
use App\Models\Server;
use App\Services\AgentDeploymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->withoutMiddleware();
});

function profileServer(string $id, array $overrides = []): Server
{
    return Server::create(array_merge([
        'server_id' => $id,
        'name' => Str::title(str_replace('-', ' ', $id)),
        'is_active' => true,
        'alerts_enabled' => true,
    ], $overrides));
}

function profileApplication(string $code = 'profile-app'): Application
{
    return Application::create([
        'name' => Str::title(str_replace('-', ' ', $code)),
        'code' => $code,
        'environment' => 'production',
        'status' => 'active',
    ]);
}

test('app server config enables IIS logs and disables database checks', function () {
    $server = profileServer('app-profile-01');
    $application = profileApplication('app-only');
    $application->servers()->attach($server->id, ['role' => 'application', 'is_required' => true]);

    $config = app(AgentDeploymentService::class)->buildConfig($server, 'plain-key');

    expect($config['deploymentProfile']['name'])->toBe('Windows IIS/ColdFusion App Server')
        ->and($config['featureFlags']['iisLogs'])->toBeTrue()
        ->and($config['iisLogs']['enabled'])->toBeTrue()
        ->and($config['featureFlags']['databaseCheck'])->toBeFalse()
        ->and($config['featureFlags']['networkChecks'])->toBeTrue()
        ->and($config['windowsServices'])->toContain('W3SVC', 'WAS', 'IISADMIN', 'ColdFusion 2023 Application Server')
        ->and($config['windowsServices'])->not->toContain('MySQL80');
});

test('db server config disables IIS logs and enables database checks', function () {
    $server = profileServer('db-profile-01');
    $application = profileApplication('db-only');
    $application->servers()->attach($server->id, ['role' => 'database', 'is_required' => true]);

    $config = app(AgentDeploymentService::class)->buildConfig($server, 'plain-key');

    expect($config['deploymentProfile']['name'])->toBe('Windows MySQL DB Server')
        ->and($config['featureFlags']['iisLogs'])->toBeFalse()
        ->and($config['iisLogs']['enabled'])->toBeFalse()
        ->and($config['featureFlags']['databaseCheck'])->toBeTrue()
        ->and($config['featureFlags']['networkChecks'])->toBeTrue()
        ->and($config['windowsServices'])->toContain('MySQL80')
        ->and($config['windowsServices'])->not->toContain('W3SVC');
});

test('single server app and database config enables both IIS logs and database checks', function () {
    $server = profileServer('app-db-profile-01');
    $application = profileApplication('app-db');
    $application->servers()->attach($server->id, ['role' => 'application', 'is_required' => true]);
    $application->servers()->attach($server->id, ['role' => 'database', 'is_required' => true]);

    $config = app(AgentDeploymentService::class)->buildConfig($server, 'plain-key');

    expect($config['deploymentProfile']['name'])->toBe('Windows App + DB Server')
        ->and($config['featureFlags']['iisLogs'])->toBeTrue()
        ->and($config['iisLogs']['enabled'])->toBeTrue()
        ->and($config['featureFlags']['databaseCheck'])->toBeTrue()
        ->and($config['windowsServices'])->toContain('W3SVC', 'ColdFusion 2023 Application Server', 'MySQL80');
});

test('cluster mapping generates packages with correct per server profiles', function () {
    $appServer = profileServer('cluster-app-01', ['agent_version' => '1.0.0']);
    $dbServer = profileServer('cluster-db-01', ['agent_version' => '1.0.0']);
    $application = profileApplication('cluster-profile');
    $application->servers()->attach($appServer->id, ['role' => 'application', 'is_required' => true]);
    $application->servers()->attach($dbServer->id, ['role' => 'database', 'is_required' => true]);

    expect(DB::table('application_servers')->where('application_id', $application->id)->count())->toBe(2);

    $response = $this->get(route('applications.agent-packages', $application));

    $response->assertOk();

    $bundlePath = $response->baseResponse->getFile()->getPathname();
    $bundle = new ZipArchive();
    expect($bundle->open($bundlePath))->toBeTrue();

    $appPackage = 'ServerMonitorAgent-Cluster-App-01-v1.0.0.zip';
    $dbPackage = 'ServerMonitorAgent-Cluster-Db-01-v1.0.0.zip';

    expect($bundle->locateName($appPackage))->not->toBeFalse()
        ->and($bundle->locateName($dbPackage))->not->toBeFalse();

    $appConfig = nestedPackageConfig($bundle->getFromName($appPackage));
    $dbConfig = nestedPackageConfig($bundle->getFromName($dbPackage));

    expect($appConfig['featureFlags']['iisLogs'])->toBeTrue()
        ->and($appConfig['featureFlags']['databaseCheck'])->toBeFalse()
        ->and($dbConfig['featureFlags']['iisLogs'])->toBeFalse()
        ->and($dbConfig['featureFlags']['databaseCheck'])->toBeTrue();

    $bundle->close();

    expect(AgentDeploymentAudit::where('server_id', $appServer->id)->where('action', 'package_downloaded')->exists())->toBeTrue()
        ->and(AgentDeploymentAudit::where('server_id', $dbServer->id)->where('action', 'package_downloaded')->exists())->toBeTrue();
});

test('manual override is respected and audited', function () {
    $server = profileServer('manual-profile-01');
    $application = profileApplication('manual-override');
    $application->servers()->attach($server->id, ['role' => 'application', 'is_required' => true]);

    $query = http_build_query([
        'featureFlags' => [
            'systemMetrics' => '1',
            'windowsServices' => '1',
            'networkChecks' => '1',
        ],
        'windowsServices' => "MySQL80\nCustomService",
    ]);

    $response = $this->get(route('servers.agent-config', $server).'?'.$query);

    $response->assertOk();
    $config = json_decode($response->getContent(), true);

    expect($config['featureFlags']['iisLogs'])->toBeFalse()
        ->and($config['iisLogs']['enabled'])->toBeFalse()
        ->and($config['windowsServices'])->toBe(['MySQL80', 'CustomService'])
        ->and(AgentDeploymentAudit::where('server_id', $server->id)->where('action', 'agent_profile_manual_override')->exists())->toBeTrue();
});

function nestedPackageConfig(string $packageContents): array
{
    $path = tempnam(sys_get_temp_dir(), 'agent-package-');
    file_put_contents($path, $packageContents);

    $zip = new ZipArchive();
    expect($zip->open($path))->toBeTrue();

    $config = json_decode($zip->getFromName('config.json'), true);
    $zip->close();
    @unlink($path);

    return $config;
}
