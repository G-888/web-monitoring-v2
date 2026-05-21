<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_reports', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('report_type', 20);
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->foreignId('application_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 40)->default('completed');
            $table->json('summary')->nullable();
            $table->string('file_path')->nullable();
            $table->timestamps();

            $table->index(['report_type', 'period_start', 'period_end']);
            $table->index(['generated_by', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_reports');
    }
};
