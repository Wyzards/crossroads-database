<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Project;
use App\Models\ProjectLink;
use App\Models\ProjectAttachment;
use App\Models\ProjectStaff;
use App\Models\CrossroadsUser;

class MigrateLegacyData extends Command
{
    protected $signature = 'migrate:legacy';
    protected $description = 'Migrate legacy DB into new schema';

    public function handle()
    {
        $this->info("Starting migration...");

        // -------------------------
        // STATUS MAP
        // -------------------------
        $statusMap = [
            0 => 'playable',
            1 => 'in_development',
            2 => 'archived',
            3 => 'honorable_mention',
            4 => 'hidden',
        ];

        // -------------------------
        // STAFF RANK MAP
        // -------------------------
        $rankMap = [
            0 => 'lead',
            1 => 'staff',
        ];

        // -------------------------
        // STEP 1: USERS
        // -------------------------
        $this->info("Migrating users...");

        $legacyUsers = DB::connection('legacy')
            ->table('Project_Staff')
            ->distinct()
            ->pluck('user_id');

        $userMap = [];

        foreach ($legacyUsers as $discordId)
        {
            $user = CrossroadsUser::firstOrCreate([
                'discord_id' => $discordId
            ]);

            $userMap[$discordId] = $user->id;
        }

        // -------------------------
        // STEP 2: PROJECTS
        // -------------------------
        $this->info("Migrating projects...");

        $projects = DB::connection('legacy')->table('Projects')->get();

        foreach ($projects as $p)
        {
            $project = Project::create([
                'id' => $p->project_id, // preserve ID
                'channel_id' => $p->channel_id,
                'guild_id' => $p->guild_id,
                'emoji' => $p->emoji,
                'name' => $p->name,
                'display_name' => $p->display_name,
                'status' => $statusMap[$p->status] ?? 'unknown',
                'description' => $p->description,
                'ip' => $p->ip,
                'role_id' => $p->role_id,
                'type' => $p->type,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => $p->deleted ? now() : null,
            ]);
        }

        // -------------------------
        // STEP 3: LINKS
        // -------------------------
        $this->info("Migrating links...");

        $links = DB::connection('legacy')->table('Project_Links')->get();

        foreach ($links as $l)
        {
            ProjectLink::create([
                'project_id' => $l->project_id,
                'label' => $l->link_name,
                'url' => $l->link_url,
            ]);
        }

        // -------------------------
        // STEP 4: ATTACHMENTS
        // -------------------------
        $this->info("Migrating attachments...");

        $attachments = DB::connection('legacy')->table('Project_Attachments')->get();

        foreach ($attachments as $a)
        {
            ProjectAttachment::create([
                'project_id' => $a->project_id,
                'path' => $a->url,
            ]);
        }

        // -------------------------
        // STEP 5: STAFF
        // -------------------------
        $this->info("Migrating staff...");

        $staff = DB::connection('legacy')->table('Project_Staff')->get();

        foreach ($staff as $s)
        {
            if (!isset($userMap[$s->user_id]))
                continue;

            ProjectStaff::create([
                'project_id' => $s->project_id,
                'user_id' => $userMap[$s->user_id],
                'rank' => $rankMap[$s->staff_rank] ?? 'staff',
            ]);
        }

        $this->info("Migration complete!");
    }
}
