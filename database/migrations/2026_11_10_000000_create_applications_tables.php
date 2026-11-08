<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Project applications (pipeline v1): a proposed listing with a working copy the applicant edits and
 * a snapshot they submit. Reviewers decide on the snapshot; accepting creates (or updates) the project.
 * Every decision is a row in application_reviews, so rejections survive reopening and re-applications
 * can be flagged.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->string('applicant_discord_id');
            // draft (editing; also after a reject that allows resubmitting), submitted, accepted, rejected (final), withdrawn
            $table->string('status')->default('draft');
            $table->string('relationship');          // owner | staff (non-team members are sent to project hunt instead)
            $table->json('working');                 // the applicant's copy, see ApplicationContent
            $table->json('snapshot')->nullable();    // what reviewers see: the last submitted copy
            $table->unsignedInteger('revision')->default(0); // bumps on every submit; decisions must name it
            $table->timestamp('submitted_at')->nullable();
            $table->string('thread_id')->nullable();         // the applicant's private thread
            $table->string('card_message_id')->nullable();   // the card in that thread
            $table->string('review_thread_id')->nullable();  // the reviewers' forum thread
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete(); // set on accept, or an unlisted project this re-applies for
            $table->timestamps();
            $table->index(['applicant_discord_id', 'status']);
        });

        Schema::create('application_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('name');
            $table->string('content_type')->nullable();
            $table->timestamps();
        });

        Schema::create('application_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision');
            // accept_watchlist | accept_vetting | accept_hunt | reject_resubmit | reject_final
            $table->string('decision');
            $table->text('reason')->nullable();
            $table->string('reviewer_discord_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_reviews');
        Schema::dropIfExists('application_files');
        Schema::dropIfExists('applications');
    }
};
