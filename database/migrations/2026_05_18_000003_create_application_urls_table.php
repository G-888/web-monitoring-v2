<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('application_urls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->foreignId('monitor_id')->nullable()->constrained('monitors')->nullOnDelete();
            $table->string('url')->nullable();
            $table->string('status')->default('unknown');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('application_urls');
    }
};
