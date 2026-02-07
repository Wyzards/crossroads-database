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
        Schema::create('project_staff', function (Blueprint $table) {
            $table->id();

            // FK to your users table (crossroads_users)
            $table->unsignedBigInteger('user_id');

            // FK to projects table
            $table->unsignedBigInteger('project_id');

            // Staff rank as integer (can map to your enum)
            $table->string('rank')->default("staff");

            $table->timestamps();

            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('crossroads_users')->onDelete('cascade');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');

            // Prevent duplicate user-project assignments
            $table->unique(['user_id', 'project_id']);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_staff');
    }
};
