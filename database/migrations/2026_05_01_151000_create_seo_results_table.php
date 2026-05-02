<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_suspicious')->default(false);
            $table->json('detected_patterns')->nullable();
            $table->timestamp('checked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_results');
    }
};
