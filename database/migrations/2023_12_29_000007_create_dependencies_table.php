<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('dependencies', function (Blueprint $table) {
            $table->id();
            $table->string('source_model');
            $table->string('target_model');
            $table->string('rule');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('dependencies');
    }
};