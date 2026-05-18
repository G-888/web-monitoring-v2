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
