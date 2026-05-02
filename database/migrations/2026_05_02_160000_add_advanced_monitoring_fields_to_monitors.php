<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->json('alert_emails')->nullable();
            $table->timestamp('ssl_expires_at')->nullable();
            $table->string('ssl_issuer')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->dropColumn(['alert_emails', 'ssl_expires_at', 'ssl_issuer']);
        });
    }
};
