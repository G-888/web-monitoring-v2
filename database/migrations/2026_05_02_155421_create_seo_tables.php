<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitor_id')->constrained()->onDelete('cascade');
            $table->string('url');
            $table->string('status')->default('clean'); // clean, suspicious, infected
            $table->json('findings')->nullable(); // Pattern matches, keyword hits
            $table->json('diffs')->nullable(); // UA hash differences
            $table->timestamp('scanned_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_scans');
    }
};
