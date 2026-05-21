<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('windows_service_commands', function (Blueprint $table) {
            $table->foreignId('requested_by')->nullable()->after('windows_service_id')->constrained('users')->nullOnDelete();
            $table->string('request_ip')->nullable()->after('requested_by');
            $table->index(['requested_by', 'created_at'], 'windows_service_commands_requested_idx');
        });
    }

    public function down(): void
    {
        Schema::table('windows_service_commands', function (Blueprint $table) {
            $table->dropIndex('windows_service_commands_requested_idx');
            $table->dropConstrainedForeignId('requested_by');
            $table->dropColumn('request_ip');
        });
    }
};
