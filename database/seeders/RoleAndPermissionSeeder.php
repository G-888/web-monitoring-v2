<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        Permission::firstOrCreate(['name' => 'view monitors']);
        Permission::firstOrCreate(['name' => 'create monitors']);
        Permission::firstOrCreate(['name' => 'edit monitors']);
        Permission::firstOrCreate(['name' => 'delete monitors']);
        Permission::firstOrCreate(['name' => 'view logs']);
        Permission::firstOrCreate(['name' => 'analyze logs']);
        Permission::firstOrCreate(['name' => 'manage users']);
        Permission::firstOrCreate(['name' => 'manage roles']);

        // Module Permissions
        Permission::firstOrCreate(['name' => 'module.advanced_alerts']);
        Permission::firstOrCreate(['name' => 'module.advanced_monitors']);
        Permission::firstOrCreate(['name' => 'module.server_metrics']);
        Permission::firstOrCreate(['name' => 'module.service_control']);
        Permission::firstOrCreate(['name' => 'module.database_monitoring']);
        Permission::firstOrCreate(['name' => 'module.log_ingestion']);
        Permission::firstOrCreate(['name' => 'module.application_mapping']);

        // Create roles and assign created permissions
        
        $viewerRole = Role::firstOrCreate(['name' => 'Viewer']);
        $viewerRole->syncPermissions(['view monitors', 'view logs']);

        $managerRole = Role::firstOrCreate(['name' => 'Manager']);
        $managerRole->syncPermissions([
            'view monitors',
            'create monitors',
            'edit monitors',
            'view logs',
            'analyze logs'
        ]);

        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin']);
        $superAdminRole->syncPermissions(Permission::all());

        // Migrate existing users based on old `is_admin` column
        // Assuming the column still exists. We will remove it in a later migration.
        if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'is_admin')) {
            $admins = User::where('is_admin', true)->get();
            foreach ($admins as $admin) {
                if (!$admin->hasRole('Super Admin')) {
                    $admin->assignRole($superAdminRole);
                }
            }

            $users = User::where('is_admin', false)->get();
            foreach ($users as $user) {
                if (!$user->hasAnyRole(['Viewer', 'Manager', 'Super Admin'])) {
                    $user->assignRole($viewerRole);
                }
            }
        }
    }
}
