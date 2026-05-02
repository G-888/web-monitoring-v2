<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_discovered_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitor_id')->constrained()->onDelete('cascade');
            $table->string('url');
            $table->string('hash')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->boolean('is_baseline')->default(false);
            $table->timestamps();

            $table->unique(['monitor_id', 'url']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_discovered_pages');
    }
};
