<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        foreach (['module.reports.view', 'module.reports.generate', 'module.reports.download'] as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission, 'guard_name' => 'web'],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }

        $superAdminRole = DB::table('roles')->where('name', 'Super Admin')->first();

        if ($superAdminRole) {
            $permissionIds = DB::table('permissions')
                ->whereIn('name', ['module.reports.view', 'module.reports.generate', 'module.reports.download'])
                ->pluck('id');

            foreach ($permissionIds as $permissionId) {
                DB::table('role_has_permissions')->updateOrInsert([
                    'permission_id' => $permissionId,
                    'role_id' => $superAdminRole->id,
                ]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        DB::table('permissions')
            ->whereIn('name', ['module.reports.view', 'module.reports.generate', 'module.reports.download'])
            ->delete();
    }
};
