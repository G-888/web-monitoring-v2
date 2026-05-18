<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iis_log_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained('servers')->cascadeOnDelete();
            $table->string('agent_server_id');
            $table->timestamp('window_start')->nullable();
            $table->timestamp('window_end')->nullable();
            $table->unsignedInteger('files_scanned')->default(0);
            $table->unsignedInteger('lines_scanned')->default(0);
            $table->unsignedInteger('total_requests')->default(0);
            $table->unsignedInteger('status_2xx')->default(0);
            $table->unsignedInteger('status_3xx')->default(0);
            $table->unsignedInteger('status_4xx')->default(0);
            $table->unsignedInteger('status_5xx')->default(0);
            $table->unsignedInteger('http_404')->default(0);
            $table->unsignedInteger('http_500')->default(0);
            $table->unsignedInteger('suspicious_count')->default(0);
            $table->json('top_ips')->nullable();
            $table->json('top_urls')->nullable();
            $table->json('parser_errors')->nullable();
            $table->timestamps();

            $table->index(['server_id', 'window_start']);
            $table->index('agent_server_id');
            $table->index('window_start');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iis_log_summaries');
    }
};
