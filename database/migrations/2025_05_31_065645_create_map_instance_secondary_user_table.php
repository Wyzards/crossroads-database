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
        Schema::create('map_instance_secondary_user', function (Blueprint $table) {
            $table->unsignedBigInteger('map_instance_id');
            $table->unsignedBigInteger('crossroads_user_id');

            $table->foreign('map_instance_id')->references('id')->on('map_instances')->onDelete('cascade');
            $table->foreign('crossroads_user_id')->references('id')->on('crossroads_users')->onDelete('cascade');

            $table->unique(['map_instance_id', 'crossroads_user_id']); // prevent duplicates
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('instance_user_secondary');
    }
};
