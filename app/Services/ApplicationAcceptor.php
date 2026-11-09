<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationReview;
use App\Models\CrossroadsUser;
use App\Models\Project;
use App\Models\Tag;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Accepting an application = writing its snapshot into a project in one transaction: a new project,
 * or the unlisted one it re-applies for. The route sets the standing (the bot's sync then builds
 * the Discord side from it):
 * - vetting:   approved, vetting_queue (blue until vetted). Free-to-play projects.
 * - hunt:      hidden, project_hunt_queue: free-to-play projects reviewers think may not survive
 *              vetting; the hunt-queue list posts them for an up/down vote.
 * - watchlist: approved, unvetted (blue). Projects that can't be vetted: not free to play
 *              (in development, closed test, whitelist or paid). Ryan, 2026-11-08.
 */
class ApplicationAcceptor
{
    public const ROUTES = [
        'watchlist' => ['architect_approval' => 'approved', 'community_vetted' => 'unvetted', 'decision' => ApplicationReview::ACCEPT_WATCHLIST],
        'vetting' => ['architect_approval' => 'approved', 'community_vetted' => 'vetting_queue', 'decision' => ApplicationReview::ACCEPT_VETTING],
        'hunt' => ['architect_approval' => 'hidden', 'community_vetted' => 'project_hunt_queue', 'decision' => ApplicationReview::ACCEPT_HUNT],
    ];

    /** Routes the snapshot allows: free to play -> vetting or hunt; anything else -> watchlist. */
    public static function allowedRoutes(array $s): array
    {
        $public = ($s['accessibility'] ?? null) === 'public';
        return $public ? ['vetting', 'hunt'] : ['watchlist'];
    }

    /** @return array{0: Project, 1: string[]} the project and notes (e.g. skipped unknown tags) */
    public static function accept(Application $application, string $route, string $reviewerDiscordId): array
    {
        $s = $application->snapshot;
        $standing = self::ROUTES[$route];
        $notes = [];
        $oldPaths = [];

        $project = DB::transaction(function () use ($application, $s, $standing, $reviewerDiscordId, &$notes, &$oldPaths) {
            $project = $application->project_id ? Project::find($application->project_id) : null;

            $fields = [
                'display_name' => $s['name'],
                'format' => $s['format'],
                'project_stage' => $s['project_stage'],
                'accessibility' => $s['accessibility'],
                'ip' => $s['ip'] ?? null,
                'download_url' => $s['download_url'] ?? null,
                'version' => $s['version'] ?? null,
                'join_method' => $s['join_method'] ?? null,
                'network_name' => $s['network_name'] ?? null,
                'join_instructions' => $s['join_instructions'] ?? null,
                'discord_kind' => $s['discord_kind'] ?? null,
                'discord_invite' => $s['discord_invite'] ?? null,
                'guild_id' => $s['guild_id'] ?? null,
                'has_dedicated_announcements' => $s['has_dedicated_announcements'] ?? null,
                'architect_approval' => $standing['architect_approval'],
                'community_vetted' => $standing['community_vetted'],
            ];

            if ($project) {
                $project->update($fields);
                $notes[] = "updated existing project {$project->name}";
            } else {
                $project = Project::create($fields + ['name' => self::uniqueName($s['name'])]);
            }

            // Content: the snapshot replaces what was there.
            $project->links()->delete();
            foreach ($s['links'] ?? [] as $link)
                $project->links()->create(['label' => $link['label'], 'url' => $link['url']]);

            $project->staff()->delete();
            foreach ($s['staff'] ?? [] as $member) {
                $user = CrossroadsUser::firstOrCreate(['discord_id' => $member['discord_id']]);
                $project->staff()->create(['user_id' => $user->id, 'rank' => $member['rank']]);
            }

            $tags = Tag::all()->keyBy(fn($t) => mb_strtolower($t->name));
            $sync = [];
            foreach ($s['tags']['secondary'] ?? [] as $name)
                if ($tag = $tags->get(mb_strtolower($name))) $sync[$tag->id] = ['position' => null];
                else $notes[] = "skipped unknown tag $name";
            foreach (array_values($s['tags']['primary'] ?? []) as $i => $name)
                if ($tag = $tags->get(mb_strtolower($name))) $sync[$tag->id] = ['position' => $i + 1];
                else $notes[] = "skipped unknown tag $name";
            $project->tags()->sync($sync);

            $oldPaths = self::replaceFiles($application, $project, $s);

            $application->update(['status' => Application::ACCEPTED, 'project_id' => $project->id]);
            $application->reviews()->create([
                'revision' => $application->revision,
                'decision' => $standing['decision'],
                'reviewer_discord_id' => $reviewerDiscordId,
            ]);

            return $project;
        });

        // Old files go only once the new content is committed.
        Storage::disk('local')->delete($oldPaths);

        return [$project, $notes];
    }

    /** Copies the snapshot's banner and section files into the project, replacing its old ones. Returns the old paths to delete after commit. */
    private static function replaceFiles(Application $application, Project $project, array $s): array
    {
        $disk = Storage::disk('local');
        $old = $project->attachments()->get();
        $project->update(['banner_attachment_id' => null]);
        $project->sections()->delete(); // cascades section_id attachments rows
        foreach ($old as $a)
            $a->delete();

        $files = $application->files()->get()->keyBy('id');
        $copy = function (int $fileId, ?int $sectionId) use ($files, $disk, $project) {
            $file = $files->get($fileId);
            if (!$file || !$disk->exists($file->path))
                return null;
            $ext = pathinfo($file->name, PATHINFO_EXTENSION);
            $path = "projects/{$project->id}/attachments/" . \Illuminate\Support\Str::random(40) . ($ext ? ".$ext" : '');
            $disk->copy($file->path, $path);
            return $project->attachments()->create(['section_id' => $sectionId, 'path' => $path]);
        };

        if (!empty($s['banner_file_id']) && ($banner = $copy((int) $s['banner_file_id'], null)))
            $project->update(['banner_attachment_id' => $banner->id]);

        foreach (array_values($s['sections'] ?? []) as $i => $section) {
            $text = trim($section['text'] ?? '');
            $ids = $section['file_ids'] ?? [];
            if ($text === '' && empty($ids))
                continue;
            $row = $project->sections()->create(['position' => $i + 1, 'text' => $text === '' ? null : $text]);
            foreach ($ids as $id)
                $copy((int) $id, $row->id);
        }

        // Positions stay 1..n even if empty sections were skipped.
        foreach ($project->sections()->get()->values() as $i => $row)
            $row->update(['position' => $i + 1]);

        return $old->pluck('path')->all();
    }

    /** name = slug of the display name, made unique (the channel name; includes deleted projects). */
    private static function uniqueName(string $displayName): string
    {
        $base = ApplicationContent::slug($displayName);
        $name = $base;
        for ($n = 2; Project::withTrashed()->where('name', $name)->exists(); $n++)
            $name = "$base-$n";
        return $name;
    }
}
