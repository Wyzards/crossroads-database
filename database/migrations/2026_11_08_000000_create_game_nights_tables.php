<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Game nights and who attended them: the record behind /gamenight, and the history for
 * future "attend N game nights" achievements. XP/badges stay in their own tables.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('game_nights', function (Blueprint $table) {
            $table->id();
            $table->string('type');                        // vetting | hunt
            $table->string('status')->default('active');   // active | ended | cancelled
            $table->foreignId('started_by_user_id')->nullable()->constrained('crossroads_users')->nullOnDelete();
            $table->string('channel_id')->nullable();          // voice channel attendance was taken in
            $table->string('discord_event_id')->nullable();    // scheduled event used for RSVPs
            $table->string('discord_event_name')->nullable();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('game_night_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_night_id')->constrained('game_nights')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('crossroads_users')->cascadeOnDelete();
            $table->timestamp('joined_at')->useCurrent();
            $table->boolean('rsvp')->default(false);           // clicked "Interested" on the event
            $table->unique(['game_night_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_night_attendees');
        Schema::dropIfExists('game_nights');
    }
};
