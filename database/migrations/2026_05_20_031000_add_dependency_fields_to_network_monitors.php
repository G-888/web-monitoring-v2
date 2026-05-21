<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('network_monitors', function (Blueprint $table) {
            $table->foreignId('target_server_id')->nullable()->after('source_server_id')->constrained('servers')->nullOnDelete();
            $table->string('dependency_type', 50)->nullable()->after('source_type');
            $table->string('protocol', 10)->default('tcp')->after('type');
            $table->timestamp('maintenance_starts_at')->nullable()->after('last_alert_at');
            $table->timestamp('maintenance_ends_at')->nullable()->after('maintenance_starts_at');

            $table->index(['target_server_id', 'last_status']);
            $table->index(['dependency_type', 'last_status']);
            $table->index(['maintenance_starts_at', 'maintenance_ends_at']);
        });
    }

    public function down(): void
    {
        Schema::table('network_monitors', function (Blueprint $table) {
            $table->dropIndex(['target_server_id', 'last_status']);
            $table->dropIndex(['dependency_type', 'last_status']);
            $table->dropIndex(['maintenance_starts_at', 'maintenance_ends_at']);
            $table->dropConstrainedForeignId('target_server_id');
            $table->dropColumn([
                'dependency_type',
                'protocol',
                'maintenance_starts_at',
                'maintenance_ends_at',
            ]);
        });
    }
};
