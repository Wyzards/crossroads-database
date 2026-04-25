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
        Schema::create('user_badge_xp', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('crossroads_users')
                ->cascadeOnDelete();

            $table->foreignId('badge_id')
                ->constrained('badges')
                ->cascadeOnDelete();

            $table->integer('total_xp')->default(0);
            $table->integer('level')->default(0);
            $table->timestamp('last_calculated_at')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'badge_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_badge_xp');
    }
};
