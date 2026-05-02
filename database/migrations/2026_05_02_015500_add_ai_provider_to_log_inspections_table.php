<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('log_inspections', function (Blueprint $table) {
            $table->string('ai_provider')->nullable()->after('ai_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('log_inspections', function (Blueprint $table) {
            $table->dropColumn('ai_provider');
        });
    }
};
