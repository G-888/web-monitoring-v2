<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('windows_services', function (Blueprint $table) {
            $table->boolean('is_monitored')->default(true)->after('startup_type');
        });

        Schema::create('windows_service_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained('servers')->cascadeOnDelete();
            $table->foreignId('windows_service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('service_name');
            $table->string('action');
            $table->string('status')->default('queued');
            $table->text('output')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['server_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('windows_service_commands');

        Schema::table('windows_services', function (Blueprint $table) {
            $table->dropColumn('is_monitored');
        });
    }
};
