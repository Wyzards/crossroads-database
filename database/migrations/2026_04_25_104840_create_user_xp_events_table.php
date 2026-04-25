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
        Schema::create('user_xp_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('crossroads_users')
                ->cascadeOnDelete();

            $table->foreignId('xp_event_definition_id')
                ->constrained('xp_event_definitions')
                ->cascadeOnDelete();

            $table->integer('xp_amount');
            $table->timestamp('created_at')->useCurrent();

            // no updated_at needed
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_xp_events');
    }
};
