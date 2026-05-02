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
        Schema::create('log_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('mime_type')->nullable();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('total_lines')->default(0);
            $table->unsignedInteger('critical_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->unsignedInteger('warning_count')->default(0);
            $table->unsignedInteger('info_count')->default(0);
            $table->json('highlights')->nullable();
            $table->timestamp('inspected_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('log_inspections');
    }
};
