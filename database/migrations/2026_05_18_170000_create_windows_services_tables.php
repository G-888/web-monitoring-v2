<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('windows_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained('servers')->cascadeOnDelete();
            $table->string('service_name');
            $table->string('display_name')->nullable();
            $table->string('status')->nullable();
            $table->string('startup_type')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_alert_at')->nullable();
            $table->timestamps();

            $table->unique(['server_id', 'service_name']);
        });

        Schema::create('windows_service_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('windows_service_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->string('startup_type')->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('windows_service_checks');
        Schema::dropIfExists('windows_services');
    }
};
