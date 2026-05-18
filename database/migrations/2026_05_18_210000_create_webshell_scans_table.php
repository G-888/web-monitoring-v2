<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webshell_scans', function (Blueprint $table) {
            $table->id();
            $table->string('source')->default('manual');
            $table->string('status')->default('pending');
            $table->text('target')->nullable();
            $table->unsignedInteger('scanned_files')->default(0);
            $table->json('findings')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('scanned_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webshell_scans');
    }
};
