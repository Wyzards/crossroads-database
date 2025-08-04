<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('channel_id')->nullable();
            $table->string('guild_id')->nullable();;
            $table->string('emoji')->nullable();
            $table->string('name');
            $table->string('display_name')->nullable();
            $table->string('status')->nullable(); // use enum in model
            $table->text('description')->nullable();
            $table->string('ip')->nullable();
            $table->string('role_id')->nullable();;
            $table->string('type')->nullable();; // use enum in model
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
