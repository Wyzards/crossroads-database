<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Channels the intake pipeline uses; the bot finds or creates them once and records them here. */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('system_channels', function (Blueprint $table) {
            $table->string('achievements_channel_id')->nullable()->change();
            $table->string('intake_channel_id')->nullable();          // #add-a-project: start panel + private application threads
            $table->string('intake_review_forum_id')->nullable();     // reviewers' forum, one thread per application
            $table->string('architects_channel_id')->nullable();      // tag requests
            $table->string('hunt_suggestions_channel_id')->nullable(); // where non-team members are pointed
        });
    }

    public function down(): void
    {
        Schema::table('system_channels', function (Blueprint $table) {
            $table->dropColumn(['intake_channel_id', 'intake_review_forum_id', 'architects_channel_id', 'hunt_suggestions_channel_id']);
        });
    }
};
