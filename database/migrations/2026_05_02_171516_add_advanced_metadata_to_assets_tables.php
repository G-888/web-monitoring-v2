<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discovered_subdomains', function (Blueprint $table) {
            $table->string('server')->nullable();
            $table->string('isp')->nullable();
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->json('tech_stack')->nullable();
            $table->json('open_ports')->nullable();
        });

        Schema::table('dns_records', function (Blueprint $table) {
            $table->string('geo_location')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('discovered_subdomains', function (Blueprint $table) {
            $table->dropColumn(['server', 'isp', 'country', 'city', 'tech_stack', 'open_ports']);
        });

        Schema::table('dns_records', function (Blueprint $table) {
            $table->dropColumn('geo_location');
        });
    }
};
