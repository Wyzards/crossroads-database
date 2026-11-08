<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pipeline v1 content model (feature-audit/PIPELINE_V1_PLAN.md): format, tags, sections, banner,
 * joining and Discord fields. Schema only; existing data is moved by `php artisan content:migrate-v1`.
 * projects.type and projects.description stay (unused) until the old code can no longer be rolled back to.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            // Featured tags are the forum tag set (Discord allows 20 per forum).
            $table->boolean('featured')->default(false);
            $table->timestamps();
        });

        Schema::create('project_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            // 1-3 = primary tag, in that order; null = secondary.
            $table->unsignedTinyInteger('position')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'tag_id']);
        });

        Schema::create('project_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->text('text')->nullable();
            $table->timestamps();
        });

        Schema::table('project_attachments', function (Blueprint $table) {
            $table->foreignId('section_id')->nullable()->after('project_id')
                ->constrained('project_sections')->cascadeOnDelete();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->string('format')->default('server')->after('type');
            $table->foreignId('banner_attachment_id')->nullable()->after('format')
                ->constrained('project_attachments')->nullOnDelete();
            $table->string('join_method')->nullable()->after('ip');
            $table->string('network_name')->nullable()->after('join_method');
            $table->string('join_instructions')->nullable()->after('network_name');
            $table->string('discord_kind')->nullable()->after('guild_id');
            $table->string('discord_invite')->nullable()->after('discord_kind');
            $table->boolean('has_dedicated_announcements')->nullable()->after('discord_invite');
        });

        Schema::table('project_lists', function (Blueprint $table) {
            // When on, every featured tag is also a forum tag of this list (rule: primary tag has it).
            $table->boolean('featured_tags')->default(false)->after('filters');
        });
    }

    public function down(): void
    {
        Schema::table('project_lists', fn(Blueprint $t) => $t->dropColumn('featured_tags'));
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('banner_attachment_id');
            $table->dropColumn(['format', 'join_method', 'network_name', 'join_instructions', 'discord_kind', 'discord_invite', 'has_dedicated_announcements']);
        });
        Schema::table('project_attachments', fn(Blueprint $t) => $t->dropConstrainedForeignId('section_id'));
        Schema::dropIfExists('project_sections');
        Schema::dropIfExists('project_tag');
        Schema::dropIfExists('tags');
    }
};
