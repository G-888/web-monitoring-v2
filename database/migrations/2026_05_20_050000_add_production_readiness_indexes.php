<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('check_results', function (Blueprint $table) {
            $table->index(['monitor_id', 'checked_at'], 'check_results_monitor_checked_idx');
        });

        Schema::table('iis_suspicious_events', function (Blueprint $table) {
            $table->index(['server_id', 'created_at'], 'iis_suspicious_server_created_idx');
            $table->index(['server_id', 'status_code', 'created_at'], 'iis_suspicious_server_status_created_idx');
        });

        Schema::table('application_servers', function (Blueprint $table) {
            $table->index(['application_id', 'server_id'], 'application_servers_app_server_idx');
            $table->index(['server_id', 'role'], 'application_servers_server_role_idx');
        });

        Schema::table('maintenance_reports', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'maintenance_reports_status_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_reports', function (Blueprint $table) {
            $table->dropIndex('maintenance_reports_status_created_idx');
        });

        Schema::table('application_servers', function (Blueprint $table) {
            $table->dropIndex('application_servers_app_server_idx');
            $table->dropIndex('application_servers_server_role_idx');
        });

        Schema::table('iis_suspicious_events', function (Blueprint $table) {
            $table->dropIndex('iis_suspicious_server_created_idx');
            $table->dropIndex('iis_suspicious_server_status_created_idx');
        });

        Schema::table('check_results', function (Blueprint $table) {
            $table->dropIndex('check_results_monitor_checked_idx');
        });
    }
};
