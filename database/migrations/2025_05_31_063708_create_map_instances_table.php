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
        Schema::create('map_instances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_id'); // Foreign key to maps
            $table->unsignedBigInteger('map_id'); // Foreign key to maps
            $table->string('docker_container_id')->nullable(); // to store container identifier
            $table->timestamps();

            $table->foreign('owner_id')->references('id')->on('crossroads_users')->onDelete('cascade');
            $table->foreign('map_id')->references('id')->on('maps')->onDelete('restrict');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('map_instances');
    }
};
