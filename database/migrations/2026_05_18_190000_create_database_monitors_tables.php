<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('database_monitors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('driver', 20)->default('mysql');
            $table->string('host');
            $table->unsignedInteger('port')->default(3306);
            $table->string('database_name');
            $table->string('username');
            $table->text('password')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('last_status')->nullable();
            $table->unsignedInteger('last_response_time_ms')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_failure_alert_at')->nullable();
            $table->unsignedInteger('alert_cooldown_seconds')->default(900);
            $table->timestamps();

            $table->index(['is_active', 'last_checked_at']);
        });

        Schema::create('database_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('database_monitor_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_up');
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();

            $table->index(['database_monitor_id', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('database_checks');
        Schema::dropIfExists('database_monitors');
    }
};
