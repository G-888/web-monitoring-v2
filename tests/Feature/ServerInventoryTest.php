<?php

use App\Models\Server;
use App\Models\User;
use Spatie\Permission\Models\Permission;

test('server inventory stores group and normalized tags', function () {
    $this->withoutMiddleware();

    Permission::firstOrCreate(['name' => 'module.server_metrics']);

    $user = User::factory()->create();
    $user->givePermissionTo('module.server_metrics');

    $this->actingAs($user)
        ->post(route('servers.store'), [
            'server_id' => 'prod-web-01',
            'name' => 'Production Web 01',
            'ip_address' => '10.0.0.10',
            'os' => 'Windows Server',
            'location' => 'Kuala Lumpur',
            'group' => 'Production',
            'tags' => 'web, mysql, web',
            'is_active' => '1',
            'alerts_enabled' => '1',
            'cpu_threshold' => 90,
            'ram_threshold' => 90,
            'disk_threshold' => 90,
            'offline_threshold_seconds' => 30,
            'alert_cooldown_seconds' => 900,
        ])
        ->assertRedirect(route('servers.index'));

    $server = Server::firstWhere('server_id', 'prod-web-01');

    expect($server)
        ->not->toBeNull()
        ->group->toBe('Production')
        ->tags->toBe(['web', 'mysql']);
});

test('server inventory stores server type along with agent settings', function () {
    $this->withoutMiddleware();

    Permission::firstOrCreate(['name' => 'module.server_metrics']);

    $user = User::factory()->create();
    $user->givePermissionTo('module.server_metrics');

    $this->actingAs($user)
        ->post(route('servers.store'), [
            'server_id' => 'prod-api-01',
            'name' => 'Production API 01',
            'server_type' => 'api',
            'ip_address' => '10.0.0.20',
            'os' => 'Linux',
            'location' => 'Singapore',
            'group' => 'Production',
            'tags' => 'api, backend',
            'is_active' => '1',
            'alerts_enabled' => '1',
            'cpu_threshold' => 80,
            'ram_threshold' => 80,
            'disk_threshold' => 80,
            'offline_threshold_seconds' => 30,
            'alert_cooldown_seconds' => 900,
        ])
        ->assertRedirect(route('servers.index'));

    $server = Server::firstWhere('server_id', 'prod-api-01');

    expect($server)
        ->not->toBeNull()
        ->server_type->toBe('api');
});

test('agent config route returns a valid JSON attachment', function () {
    $this->withoutMiddleware();

    Permission::firstOrCreate(['name' => 'module.server_metrics']);

    $user = User::factory()->create();
    $user->givePermissionTo('module.server_metrics');

    $server = Server::create([
        'server_id' => 'prod-agent-01',
        'name' => 'Agent Server',
        'group' => 'Production',
        'tags' => ['agent'],
        'is_active' => true,
        'server_type' => 'infrastructure',
    ]);

    $response = $this->actingAs($user)
        ->get(route('agents.config', $server));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/json');
    $response->assertHeader('Content-Disposition', 'attachment; filename="agent-config-prod-agent-01.json"');

    $payload = json_decode($response->getContent(), true);

    expect($payload)->toBeArray();
    expect($payload['serverId'])->toBe('prod-agent-01');
    expect($payload['apiUrl'])->toBe(url('/api/metrics'));
    expect($payload['autoDiscoverWindowsServices'])->toBeTrue();
});

test('server inventory shows agent setup details', function () {
    $this->withoutMiddleware();

    Permission::firstOrCreate(['name' => 'module.server_metrics']);

    $user = User::factory()->create();
    $user->givePermissionTo('module.server_metrics');

    Server::create([
        'server_id' => 'prod-db-01',
        'name' => 'Production DB 01',
        'group' => 'Production',
        'tags' => ['database', 'mysql'],
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get(route('servers.index'))
        ->assertOk()
        ->assertSee('Production')
        ->assertSee('database')
        ->assertSee('prod-db-01')
        ->assertSee('&lt;AGENT_API_KEY&gt;', false)
        ->assertSee(url('/api/metrics'));
});
