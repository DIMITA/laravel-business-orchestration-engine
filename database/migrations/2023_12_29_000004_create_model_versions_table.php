<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('model_versions', function (Blueprint $table) {
            $table->id();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->unsignedBigInteger('version');
            $table->json('snapshot');
            $table->string('hash');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['model_type', 'model_id', 'version']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('model_versions');
    }
};