<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iis_log_collector_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->timestamp('last_scan_at')->nullable()->index();
            $table->unsignedInteger('files_seen')->default(0);
            $table->unsignedInteger('files_read')->default(0);
            $table->unsignedInteger('lines_read')->default(0);
            $table->unsignedInteger('summaries_sent')->default(0);
            $table->text('last_error')->nullable();
            $table->string('state_file_path', 1000)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iis_log_collector_statuses');
    }
};
