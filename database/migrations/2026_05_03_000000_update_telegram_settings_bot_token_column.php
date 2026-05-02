<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $connection = Schema::getConnection()->getDriverName();

        if ($connection === 'mysql') {
            DB::statement('ALTER TABLE `telegram_settings` MODIFY `bot_token` TEXT NULL');
        } elseif ($connection === 'pgsql') {
            DB::statement('ALTER TABLE telegram_settings ALTER COLUMN bot_token TYPE TEXT');
        } elseif ($connection === 'sqlite') {
            // SQLite does not support MODIFY; use schema rebuild if needed.
            Schema::table('telegram_settings', function (Blueprint $table) {
                $table->text('bot_token')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = Schema::getConnection()->getDriverName();

        if ($connection === 'mysql') {
            DB::statement('ALTER TABLE `telegram_settings` MODIFY `bot_token` VARCHAR(255) NULL');
        } elseif ($connection === 'pgsql') {
            DB::statement('ALTER TABLE telegram_settings ALTER COLUMN bot_token TYPE VARCHAR(255)');
        } elseif ($connection === 'sqlite') {
            Schema::table('telegram_settings', function (Blueprint $table) {
                $table->string('bot_token', 255)->nullable();
            });
        }
    }
};
