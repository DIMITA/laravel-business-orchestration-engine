<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('sync_log', function (Blueprint $table) {
            $table->id();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->string('operation'); // INSERT, UPDATE, DELETE
            $table->json('changed_fields')->nullable();
            $table->unsignedBigInteger('version');
            $table->timestamps();
            $table->index(['model_type', 'model_id', 'version']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('sync_log');
    }
};