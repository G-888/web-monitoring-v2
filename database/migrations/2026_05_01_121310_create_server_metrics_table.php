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
        Schema::create('server_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('server_id');
            $table->decimal('cpu', 5, 2); // CPU usage percentage
            $table->decimal('ram_used', 10, 2); // RAM used in GB
            $table->decimal('ram_total', 10, 2); // RAM total in GB
            $table->decimal('disk_used', 10, 2); // Disk used in GB
            $table->decimal('disk_total', 10, 2); // Disk total in GB
            $table->timestamp('timestamp');
            $table->timestamps();

            $table->index(['server_id', 'timestamp']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('server_metrics');
    }
};
