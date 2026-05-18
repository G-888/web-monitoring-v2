<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iis_suspicious_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained('servers')->cascadeOnDelete();
            $table->foreignId('iis_log_summary_id')->nullable()->constrained('iis_log_summaries')->nullOnDelete();
            $table->timestamp('event_timestamp')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('method', 20)->nullable();
            $table->text('url')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->string('matched_pattern')->nullable();
            $table->text('user_agent')->nullable();
            $table->text('raw')->nullable();
            $table->timestamps();

            $table->index('server_id');
            $table->index('status_code');
            $table->index('created_at');
            $table->index('event_timestamp');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iis_suspicious_events');
    }
};
