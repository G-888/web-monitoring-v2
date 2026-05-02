<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_integrity_hashes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitor_id')->constrained()->onDelete('cascade');
            $table->string('file_path');
            $table->string('hash');
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();

            $table->unique(['monitor_id', 'file_path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_integrity_hashes');
    }
};
