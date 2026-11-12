<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily Discord invite check: one row per listed project's invite link that is currently bad
 * (dead, or pointing at another server) or a warning (temporary invite). A row exists only while
 * the problem does, so an alert is posted once, when the row is created, and a "fixed" line when
 * it is deleted.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('invite_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('url', 500);
            $table->string('status'); // bad | warning
            $table->string('reason');
            $table->timestamp('first_seen_at');
            $table->timestamps();
            $table->unique(['project_id', 'url']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invite_checks');
    }
};
