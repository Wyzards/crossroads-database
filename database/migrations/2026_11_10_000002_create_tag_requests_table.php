<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** An applicant's request for a tag that isn't in the curated list; decided in #architects. */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tag_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('requested_by_discord_id');
            $table->string('status')->default('pending'); // pending | approved | rejected
            $table->string('decided_by_discord_id')->nullable();
            $table->foreignId('tag_id')->nullable()->constrained()->nullOnDelete();
            $table->string('message_id')->nullable(); // the #architects message
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tag_requests');
    }
};
