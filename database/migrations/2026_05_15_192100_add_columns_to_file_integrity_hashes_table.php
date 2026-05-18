<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('file_integrity_hashes', function (Blueprint $table) {
            $table->foreignId('monitor_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->text('file_path')->after('monitor_id');
            $table->string('hash', 128)->after('file_path');
            $table->timestamp('last_checked_at')->nullable()->after('hash');
        });
    }

    public function down(): void
    {
        Schema::table('file_integrity_hashes', function (Blueprint $table) {
            $table->dropForeign(['monitor_id']);
            $table->dropColumn(['monitor_id', 'file_path', 'hash', 'last_checked_at']);
        });
    }
};
