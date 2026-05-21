<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'module.agent_deployment']);

        Role::where('name', 'Super Admin')->get()->each(function (Role $role) use ($permission) {
            $role->givePermissionTo($permission);
        });
    }

    public function down(): void
    {
        Permission::where('name', 'module.agent_deployment')->delete();
    }
};
