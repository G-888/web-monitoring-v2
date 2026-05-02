<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_results', function (Blueprint $table) {
            $table->json('search_findings')->nullable()->after('detected_patterns');
            $table->json('search_queries')->nullable()->after('search_findings');
        });
    }

    public function down(): void
    {
        Schema::table('seo_results', function (Blueprint $table) {
            $table->dropColumn(['search_findings', 'search_queries']);
        });
    }
};
