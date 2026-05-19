<?php

use App\Models\AgentDeploymentAudit;
use App\Models\Server;

function deploymentServer(array $overrides = []): Server
{
    return Server::create(array_merge([
        'server_id' => 'deploy-target-01',
        'name' => 'Deploy Target 01',
        'server_type' => 'app_database',
        'is_active' => true,
        'alerts_enabled' => true,
    ], $overrides));
}

test('agent config contains correct server id and does not store plain api key', function () {
    $this->withoutMiddleware();
    config(['services.agent.key' => null]);

    $server = deploymentServer();

    $response = $this->get(route('servers.agent-config', $server));

    $response->assertOk();
    $payload = json_decode($response->getContent(), true);

    expect($payload['serverId'])->toBe('deploy-target-01')
        ->and($payload['apiKey'])->toBeString()
        ->and($payload['apiKey'])->not->toBe('')
        ->and($payload['configSchemaVersion'])->toBe(config('agent.default_config_schema_version'))
        ->and($payload['windowsServices'])->toContain('W3SVC', 'MySQL80')
        ->and($server->refresh()->agent_api_key_hash)->not->toBeNull()
        ->and($server->agent_api_key_hash)->not->toBe($payload['apiKey']);

    expect(AgentDeploymentAudit::where('server_id', $server->id)->where('action', 'config_generated')->exists())->toBeTrue();
});

test('agent package includes required deployment files', function () {
    $this->withoutMiddleware();

    $server = deploymentServer(['agent_version' => '1.0.0']);

    $response = $this->get(route('servers.agent-package', $server));

    $response->assertOk();

    $zipPath = $response->baseResponse->getFile()->getPathname();
    $zip = new ZipArchive();
    expect($zip->open($zipPath))->toBeTrue();

    foreach ([
        'server-monitor-agent.exe',
        'config.json',
        'install-service.ps1',
        'uninstall-service.ps1',
        'restart-agent.ps1',
        'update-agent.ps1',
        'README.txt',
        'logs/README.txt',
    ] as $file) {
        expect($zip->locateName($file))->not->toBeFalse("Missing {$file}");
    }

    $config = json_decode($zip->getFromName('config.json'), true);
    expect($config['serverId'])->toBe('deploy-target-01');

    $installScript = $zip->getFromName('install-service.ps1');
    expect($installScript)->toBeString()
        ->and($installScript)->toContain('.\server-monitor-agent.exe')
        ->and($installScript)->toContain('.\config.json')
        ->and($installScript)->not->toContain('..\dist')
        ->and($installScript)->not->toContain('config.json.template');

    $zip->close();

    expect(AgentDeploymentAudit::where('server_id', $server->id)->where('action', 'package_downloaded')->exists())->toBeTrue();
});

test('rotated server key invalidates old per server key', function () {
    $this->withoutMiddleware();
    config(['services.agent.key' => null]);

    $server = deploymentServer();
    $payload = json_decode($this->get(route('servers.agent-config', $server))->getContent(), true);
    $oldKey = $payload['apiKey'];

    $metricPayload = [
        'server_id' => 'deploy-target-01',
        'cpu' => 12.5,
        'ram_used' => 4,
        'ram_total' => 16,
        'disk_used' => 40,
        'disk_total' => 200,
        'timestamp' => now()->toISOString(),
    ];

    $this->postJson('/api/metrics', $metricPayload, [
        'X-API-Key' => $oldKey,
    ])->assertAccepted();

    $this->post(route('servers.agent-key.rotate', $server))
        ->assertRedirect();

    $this->postJson('/api/metrics', $metricPayload, [
        'X-API-Key' => $oldKey,
    ])->assertUnauthorized();

    expect(AgentDeploymentAudit::where('server_id', $server->id)->where('action', 'agent_key_rotated')->exists())->toBeTrue();
});
