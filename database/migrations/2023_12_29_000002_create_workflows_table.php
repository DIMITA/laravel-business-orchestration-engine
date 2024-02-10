<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('workflow_instances', function (Blueprint $table) {
            $table->id();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->string('state');
            $table->timestamps();
            $table->index(['model_type', 'model_id']);
        });

        Schema::create('workflow_transitions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('from');
            $table->string('to');
            $table->string('guard_expression')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('workflow_transitions');
        Schema::dropIfExists('workflow_instances');
    }
};