<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('environment')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('support_team')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['environment', 'status']);
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('architecture_type')->nullable()->after('status');
            $table->json('technology_stack_json')->nullable()->after('architecture_type');

            $table->index(['client_id', 'environment']);
            $table->index('architecture_type');
        });

        Schema::table('maintenance_reports', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('application_id')->constrained()->nullOnDelete();
            $table->index('client_id');
        });

        Schema::table('database_monitors', function (Blueprint $table) {
            $table->foreignId('application_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('server_id')->nullable()->after('application_id')->constrained()->nullOnDelete();

            $table->index(['application_id', 'last_status']);
            $table->index('server_id');
        });
    }

    public function down(): void
    {
        Schema::table('database_monitors', function (Blueprint $table) {
            $table->dropIndex(['application_id', 'last_status']);
            $table->dropIndex(['server_id']);
            $table->dropConstrainedForeignId('application_id');
            $table->dropConstrainedForeignId('server_id');
        });

        Schema::table('maintenance_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->dropIndex(['client_id', 'environment']);
            $table->dropIndex(['architecture_type']);
            $table->dropConstrainedForeignId('client_id');
            $table->dropColumn(['architecture_type', 'technology_stack_json']);
        });

        Schema::dropIfExists('clients');
    }
};
