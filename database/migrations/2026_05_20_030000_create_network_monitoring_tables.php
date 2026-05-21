<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_monitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('source_server_id')->nullable()->constrained('servers')->nullOnDelete();
            $table->string('name');
            $table->string('type', 30);
            $table->string('source_type', 20)->default('central');
            $table->string('target_host');
            $table->unsignedInteger('target_port')->nullable();
            $table->string('dns_record_type', 20)->nullable();
            $table->text('expected_value')->nullable();
            $table->string('expected_state', 20)->default('open');
            $table->unsignedInteger('timeout_ms')->default(3000);
            $table->unsignedInteger('latency_threshold_ms')->nullable();
            $table->unsignedInteger('interval_seconds')->default(300);
            $table->boolean('is_active')->default(false);
            $table->string('last_status', 30)->nullable();
            $table->unsignedInteger('last_latency_ms')->nullable();
            $table->text('last_resolved_value')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_alert_at')->nullable();
            $table->unsignedInteger('alert_cooldown_seconds')->default(900);
            $table->timestamps();

            $table->index(['is_active', 'source_type', 'type']);
            $table->index(['application_id', 'last_status']);
            $table->index(['source_server_id', 'source_type']);
            $table->index('last_checked_at');
        });

        Schema::create('network_check_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('network_monitor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_server_id')->nullable()->constrained('servers')->nullOnDelete();
            $table->string('type', 30);
            $table->string('source_type', 20);
            $table->string('target_host');
            $table->unsignedInteger('target_port')->nullable();
            $table->string('status', 30);
            $table->boolean('is_successful')->default(false);
            $table->unsignedInteger('latency_ms')->nullable();
            $table->text('resolved_value')->nullable();
            $table->text('expected_value')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();

            $table->index(['network_monitor_id', 'checked_at']);
            $table->index(['source_server_id', 'checked_at']);
            $table->index(['status', 'checked_at']);
        });

        Schema::create('server_port_baselines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('protocol', 10)->default('tcp');
            $table->unsignedInteger('port');
            $table->string('expected_state', 20)->default('open');
            $table->string('scan_target')->nullable();
            $table->unsignedInteger('timeout_ms')->default(3000);
            $table->boolean('is_active')->default(false);
            $table->string('last_status', 30)->nullable();
            $table->unsignedInteger('last_latency_ms')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_alert_at')->nullable();
            $table->unsignedInteger('alert_cooldown_seconds')->default(900);
            $table->timestamps();

            $table->unique(['server_id', 'protocol', 'port']);
            $table->index(['is_active', 'expected_state']);
            $table->index(['server_id', 'last_status']);
        });

        Permission::firstOrCreate(['name' => 'module.network_monitoring']);

        Role::whereIn('name', ['Super Admin', 'Manager'])->get()->each(function (Role $role) {
            $role->givePermissionTo('module.network_monitoring');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_port_baselines');
        Schema::dropIfExists('network_check_results');
        Schema::dropIfExists('network_monitors');
    }
};
