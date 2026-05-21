<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('database_monitors', function (Blueprint $table) {
            $table->string('db_role', 30)->nullable()->after('server_id');
            $table->text('default_query')->nullable()->after('password');
            $table->timestamp('configured_at')->nullable()->after('last_checked_at');
            $table->timestamp('enabled_at')->nullable()->after('configured_at');

            $table->index(['db_role', 'is_active']);
            $table->index('configured_at');
        });
    }

    public function down(): void
    {
        Schema::table('database_monitors', function (Blueprint $table) {
            $table->dropIndex(['db_role', 'is_active']);
            $table->dropIndex(['configured_at']);
            $table->dropColumn(['db_role', 'default_query', 'configured_at', 'enabled_at']);
        });
    }
};
