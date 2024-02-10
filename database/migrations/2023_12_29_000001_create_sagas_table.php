<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('sagas', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('status', ['PENDING', 'RUNNING', 'FAILED', 'COMPENSATED', 'COMPLETED'])->default('PENDING');
            $table->string('current_step')->nullable();
            $table->json('payload');
            $table->timestamps();
        });

        Schema::create('saga_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saga_id')->constrained('sagas')->onDelete('cascade');
            $table->string('step_name');
            $table->enum('status', ['PENDING', 'RUNNING', 'COMPLETED', 'FAILED', 'COMPENSATED'])->default('PENDING');
            $table->timestamp('executed_at')->nullable();
            $table->timestamp('compensated_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('saga_steps');
        Schema::dropIfExists('sagas');
    }
};