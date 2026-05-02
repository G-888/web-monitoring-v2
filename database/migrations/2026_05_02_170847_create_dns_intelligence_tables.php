<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. DNS Records History
        Schema::create('dns_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitor_id')->constrained()->onDelete('cascade');
            $table->string('type'); // A, MX, TXT, NS, CNAME
            $table->string('host');
            $table->text('value');
            $table->integer('ttl')->nullable();
            $table->string('hash')->index(); // To quickly detect changes
            $table->boolean('is_baseline')->default(false);
            $table->timestamp('last_seen_at');
            $table->timestamps();
        });

        // 2. Discovered Subdomains
        Schema::create('discovered_subdomains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitor_id')->constrained()->onDelete('cascade');
            $table->string('subdomain')->index();
            $table->string('ip')->nullable();
            $table->string('source')->default('crt.sh'); // crt.sh, brute, manual
            $table->boolean('is_monitored')->default(false);
            $table->timestamps();
        });

        // 3. WHOIS Information
        Schema::create('domain_whois', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitor_id')->constrained()->onDelete('cascade');
            $table->string('registrar')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_whois');
        Schema::dropIfExists('discovered_subdomains');
        Schema::dropIfExists('dns_records');
    }
};
