<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Community Vetting cycle (design step 3): the project's open vetting post and when its vetting
 * state last changed, the runner's switch + lease (one row), one row per weekly cycle, and one row
 * per step of a cycle. Votes are not stored here: Discord owns them; a cycle stores only the
 * frozen decision.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('vetting_thread_id', 30)->nullable()->after('discovery_thread_id');
            $table->timestamp('community_vetted_at')->nullable();
        });

        // The Hunt 4-week clock starts at deploy (Hunt votes were not counted before).
        $now = now()->toDateTimeString();
        DB::table('projects')->whereIn('community_vetted', ['vetting_queue', 'project_hunt_queue'])
            ->update(['community_vetted_at' => $now]);
        DB::table('projects')->where('community_vetted', 'currently_vetting')
            ->update(['community_vetted_at' => DB::raw('updated_at')]);

        Schema::create('vetting_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->boolean('enabled')->default(false);
            $table->timestamp('enabled_at')->nullable();
            $table->string('ping_vetting_role_id', 30)->nullable();
            $table->string('ping_polls_role_id', 30)->nullable();
            $table->string('ping_gamenight_role_id', 30)->nullable();
            $table->string('ping_new_projects_role_id', 30)->nullable();
            $table->string('ping_new_archive_role_id', 30)->nullable();
            $table->string('lease_holder')->nullable();
            $table->string('lease_token', 36)->nullable();
            $table->timestamp('lease_expires_at')->nullable();
            $table->timestamps();
        });
        DB::table('vetting_settings')->insert(['id' => 1, 'enabled' => false, 'created_at' => $now, 'updated_at' => $now]);

        Schema::create('vetting_cycles', function (Blueprint $table) {
            $table->id();
            $table->date('week_of')->unique();
            $table->foreignId('chosen_project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('chosen_by')->nullable(); // oldest | only | poll | none
            $table->foreignId('closed_project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('close_thread_id', 30)->nullable();
            $table->string('close_result')->nullable(); // accepted | rejected
            $table->unsignedInteger('close_up')->nullable();
            $table->unsignedInteger('close_down')->nullable();
            $table->unsignedInteger('close_both')->nullable();
            $table->json('close_votes')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('started_project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('started_from')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->string('announce_message_id', 30)->nullable();
            $table->timestamps();
        });

        Schema::create('vetting_cycle_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('vetting_cycles')->cascadeOnDelete();
            $table->string('step');
            $table->string('status'); // running | done | skipped | failed
            $table->unsignedInteger('attempts')->default(0);
            $table->text('note')->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->unique(['cycle_id', 'step']);
        });

        Schema::table('system_channels', function (Blueprint $table) {
            $table->string('vetting_forum_id', 30)->nullable();
            $table->string('announcements_channel_id', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('system_channels', function (Blueprint $table) {
            $table->dropColumn(['vetting_forum_id', 'announcements_channel_id']);
        });
        Schema::dropIfExists('vetting_cycle_steps');
        Schema::dropIfExists('vetting_cycles');
        Schema::dropIfExists('vetting_settings');
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['vetting_thread_id', 'community_vetted_at']);
        });
    }
};
