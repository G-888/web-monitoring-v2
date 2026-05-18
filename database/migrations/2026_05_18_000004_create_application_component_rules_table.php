<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('application_component_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->string('component_type'); // e.g. 'app_server' or 'db_server'
            $table->unsignedInteger('min_required')->default(1);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('application_component_rules');
    }
};
