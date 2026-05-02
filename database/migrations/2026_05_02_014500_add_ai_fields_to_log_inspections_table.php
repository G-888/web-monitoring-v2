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
            $table->string('ai_status')->default('not_requested')->after('highlights');
            $table->string('ai_model')->nullable()->after('ai_status');
            $table->text('ai_summary')->nullable()->after('ai_model');
            $table->json('ai_findings')->nullable()->after('ai_summary');
            $table->timestamp('ai_analyzed_at')->nullable()->after('ai_findings');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('log_inspections', function (Blueprint $table) {
            $table->dropColumn([
                'ai_status',
                'ai_model',
                'ai_summary',
                'ai_findings',
                'ai_analyzed_at',
            ]);
        });
    }
};
