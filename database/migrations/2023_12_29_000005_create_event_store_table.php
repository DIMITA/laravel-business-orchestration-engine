<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('event_store', function (Blueprint $table) {
            $table->id();
            $table->string('aggregate_id');
            $table->string('event_type');
            $table->json('payload');
            $table->unsignedBigInteger('version');
            $table->timestamps();
            $table->index(['aggregate_id', 'version']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('event_store');
    }
};