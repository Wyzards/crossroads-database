<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Connect announcements": the bot joins a project's Discord server (invited by someone with Manage
 * Server), follows the announcement channels the applicant/lead picks into our hidden channels,
 * then leaves. One row per attempt: waiting (for the bot to join) -> choosing -> done | cancelled.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('announcement_connections', function (Blueprint $table) {
            $table->id();
            $table->string('guild_id');
            $table->string('guild_name')->nullable();
            $table->foreignId('application_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('requested_by_discord_id');
            $table->string('status')->default('waiting');
            // [{channel_id, name, webhook_channel_id, webhook_id}]
            $table->json('followed')->nullable();
            $table->timestamps();
            $table->index(['guild_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_connections');
    }
};
