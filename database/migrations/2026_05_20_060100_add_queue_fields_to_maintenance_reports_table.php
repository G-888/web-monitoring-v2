<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_reports', function (Blueprint $table) {
            $table->timestamp('queued_at')->nullable()->after('file_path');
            $table->timestamp('started_at')->nullable()->after('queued_at');
            $table->timestamp('completed_at')->nullable()->after('started_at');
            $table->timestamp('failed_at')->nullable()->after('completed_at');
            $table->text('error_message')->nullable()->after('failed_at');
            $table->index(['status', 'queued_at']);
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_reports', function (Blueprint $table) {
            $table->dropIndex(['status', 'queued_at']);
            $table->dropColumn(['queued_at', 'started_at', 'completed_at', 'failed_at', 'error_message']);
        });
    }
};
