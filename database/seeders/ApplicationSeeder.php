<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Application;
use App\Models\ApplicationComponentRule;
use App\Models\ApplicationUrl;
use App\Models\Server;

class ApplicationSeeder extends Seeder
{
    public function run()
    {
        $app = Application::firstOrCreate([
            'code' => 'test-app',
        ], [
            'name' => 'Test Application',
            'environment' => 'staging',
            'owner_team' => 'DevOps',
            'description' => 'Automatically seeded test application',
            'status' => 'active',
        ]);

        // component rules
        ApplicationComponentRule::firstOrCreate([
            'application_id' => $app->id,
            'component_type' => 'app_servers',
        ], [
            'min_required' => 1,
        ]);

        ApplicationComponentRule::firstOrCreate([
            'application_id' => $app->id,
            'component_type' => 'database_servers',
        ], [
            'min_required' => 1,
        ]);

        // URL
        ApplicationUrl::firstOrCreate([
            'application_id' => $app->id,
            'url' => 'http://example.test/health',
        ], [
            'status' => 'unknown',
        ]);

        // Attach an existing server if present (attach twice with different roles)
        $server = Server::first();
        if ($server) {
            $app->servers()->detach($server->id);

            // attach as app role
            $app->servers()->attach($server->id, [
                'role' => 'application',
                'is_primary' => true,
                'is_required' => true,
                'notes' => 'Seeded as app server',
            ]);

            // attach same server as db role as well (separate pivot row)
            $app->servers()->attach($server->id, [
                'role' => 'database',
                'is_primary' => true,
                'is_required' => true,
                'notes' => 'Seeded as db server',
            ]);
        }
    }
}
