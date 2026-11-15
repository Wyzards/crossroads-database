<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saturday game night (design step 6): the cycle records the Discord scheduled event the event step
 * created and the game night gn_start opened (both written once), and the system channels record the
 * voice or stage channel the event is held in.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('vetting_cycles', function (Blueprint $table) {
            $table->string('event_id', 30)->nullable();
            $table->foreignId('game_night_id')->nullable()->constrained('game_nights')->nullOnDelete();
        });
        Schema::table('system_channels', function (Blueprint $table) {
            $table->string('gamenight_channel_id', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('system_channels', function (Blueprint $table) {
            $table->dropColumn('gamenight_channel_id');
        });
        Schema::table('vetting_cycles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('game_night_id');
            $table->dropColumn('event_id');
        });
    }
};
