<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('application_servers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->unsignedBigInteger('server_id');
            $table->string('role')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_required')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('server_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('application_servers');
    }
};
