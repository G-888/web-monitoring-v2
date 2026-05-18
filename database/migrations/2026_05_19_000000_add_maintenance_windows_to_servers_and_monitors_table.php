<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->timestamp('maintenance_starts_at')->nullable()->after('last_heartbeat_at');
            $table->timestamp('maintenance_ends_at')->nullable()->after('maintenance_starts_at');
        });

        Schema::table('monitors', function (Blueprint $table) {
            $table->timestamp('maintenance_starts_at')->nullable()->after('is_active');
            $table->timestamp('maintenance_ends_at')->nullable()->after('maintenance_starts_at');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['maintenance_starts_at', 'maintenance_ends_at']);
        });

        Schema::table('monitors', function (Blueprint $table) {
            $table->dropColumn(['maintenance_starts_at', 'maintenance_ends_at']);
        });
    }
};
