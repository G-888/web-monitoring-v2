<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->unsignedInteger('iis_http_500_warning_threshold')->nullable()->after('alert_cooldown_seconds');
            $table->unsignedInteger('iis_http_500_critical_threshold')->nullable()->after('iis_http_500_warning_threshold');
            $table->unsignedInteger('iis_http_404_warning_threshold')->nullable()->after('iis_http_500_critical_threshold');
            $table->unsignedInteger('iis_http_404_critical_threshold')->nullable()->after('iis_http_404_warning_threshold');
            $table->unsignedInteger('iis_suspicious_warning_threshold')->nullable()->after('iis_http_404_critical_threshold');
            $table->unsignedInteger('iis_suspicious_critical_threshold')->nullable()->after('iis_suspicious_warning_threshold');
            $table->unsignedInteger('iis_alert_cooldown_seconds')->nullable()->after('iis_suspicious_critical_threshold');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn([
                'iis_http_500_warning_threshold',
                'iis_http_500_critical_threshold',
                'iis_http_404_warning_threshold',
                'iis_http_404_critical_threshold',
                'iis_suspicious_warning_threshold',
                'iis_suspicious_critical_threshold',
                'iis_alert_cooldown_seconds',
            ]);
        });
    }
};
