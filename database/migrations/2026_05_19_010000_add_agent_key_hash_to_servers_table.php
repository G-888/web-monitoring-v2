<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            if (! Schema::hasColumn('servers', 'agent_api_key_hash')) {
                $table->string('agent_api_key_hash')->nullable()->after('agent_version');
            }
            if (! Schema::hasColumn('servers', 'agent_api_key_rotated_at')) {
                $table->timestamp('agent_api_key_rotated_at')->nullable()->after('agent_api_key_hash');
            }
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            if (Schema::hasColumn('servers', 'agent_api_key_hash')) {
                $table->dropColumn('agent_api_key_hash');
            }
            if (Schema::hasColumn('servers', 'agent_api_key_rotated_at')) {
                $table->dropColumn('agent_api_key_rotated_at');
            }
        });
    }
};
