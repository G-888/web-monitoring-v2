<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->boolean('alerts_enabled')->default(true)->after('is_active');
            $table->decimal('cpu_threshold', 5, 2)->nullable()->after('alerts_enabled');
            $table->decimal('ram_threshold', 5, 2)->nullable()->after('cpu_threshold');
            $table->decimal('disk_threshold', 5, 2)->nullable()->after('ram_threshold');
            $table->unsignedInteger('offline_threshold_seconds')->default(15)->after('disk_threshold');
            $table->unsignedInteger('alert_cooldown_seconds')->default(900)->after('offline_threshold_seconds');
            $table->timestamp('last_cpu_alert_at')->nullable()->after('last_heartbeat_at');
            $table->timestamp('last_ram_alert_at')->nullable()->after('last_cpu_alert_at');
            $table->timestamp('last_disk_alert_at')->nullable()->after('last_ram_alert_at');
            $table->timestamp('last_offline_alert_at')->nullable()->after('last_disk_alert_at');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn([
                'alerts_enabled',
                'cpu_threshold',
                'ram_threshold',
                'disk_threshold',
                'offline_threshold_seconds',
                'alert_cooldown_seconds',
                'last_cpu_alert_at',
                'last_ram_alert_at',
                'last_disk_alert_at',
                'last_offline_alert_at',
            ]);
        });
    }
};
