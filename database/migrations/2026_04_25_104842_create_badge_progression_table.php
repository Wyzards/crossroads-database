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
        Schema::create('badge_progression', function (Blueprint $table) {
            $table->id();

            $table->foreignId('badge_id')
                ->constrained('badges')
                ->cascadeOnDelete();

            $table->integer('base_xp');
            $table->float('growth_factor');
            $table->string('curve_type'); // linear | exponential

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('badge_progression');
    }
};
