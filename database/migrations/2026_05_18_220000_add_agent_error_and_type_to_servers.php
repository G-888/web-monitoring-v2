<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            if (! Schema::hasColumn('servers', 'last_agent_error')) {
                $table->text('last_agent_error')->nullable()->after('agent_runtime');
            }
            if (! Schema::hasColumn('servers', 'server_type')) {
                $table->string('server_type')->nullable()->after('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            if (Schema::hasColumn('servers', 'last_agent_error')) {
                $table->dropColumn('last_agent_error');
            }
            if (Schema::hasColumn('servers', 'server_type')) {
                $table->dropColumn('server_type');
            }
        });
    }
};
