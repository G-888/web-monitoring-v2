<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            if (! Schema::hasColumn('servers', 'agent_version')) {
                $table->string('agent_version')->nullable()->after('is_active');
            }
            if (! Schema::hasColumn('servers', 'config_schema_version')) {
                $table->string('config_schema_version')->nullable()->after('agent_version');
            }
            if (! Schema::hasColumn('servers', 'capabilities')) {
                $table->json('capabilities')->nullable()->after('config_schema_version');
            }
            if (! Schema::hasColumn('servers', 'agent_hostname')) {
                $table->string('agent_hostname')->nullable()->after('capabilities');
            }
            if (! Schema::hasColumn('servers', 'agent_os')) {
                $table->string('agent_os')->nullable()->after('agent_hostname');
            }
            if (! Schema::hasColumn('servers', 'agent_runtime')) {
                $table->string('agent_runtime')->nullable()->after('agent_os');
            }
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn([
                'agent_version',
                'config_schema_version',
                'capabilities',
                'agent_hostname',
                'agent_os',
                'agent_runtime',
            ]);
        });
    }
};
