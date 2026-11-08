<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationReview;
use App\Models\Project;
use App\Models\ProjectList;
use App\FilterEvaluator;
use Illuminate\Support\Str;

/**
 * The shape of an application's working copy and snapshot, what "complete" means, and how an
 * application relates to existing projects. Field names match the project's, so accepting is a copy.
 *
 * {
 *   name, format (server|map), project_stage, accessibility,
 *   ip, version, join_method (direct|network), network_name, join_instructions,
 *   discord_kind (own|network|none), discord_invite, guild_id, discord_server_name, discord_member_count,
 *   has_dedicated_announcements,
 *   sections: [{text, file_ids: []}], banner_file_id,
 *   links: [{label, url}], staff: [{discord_id, rank (lead|staff)}],
 *   tags: {primary: [names, max 3], secondary: [names]}, english (bool)
 * }
 */
class ApplicationContent
{
    public const MAX_SECTIONS = 10;

    /** Validation rules for a working copy (shape only; completeness is checked at submit). */
    public static function rules(): array
    {
        return [
            'working' => 'required|array',
            'working.name' => 'nullable|string|max:60',
            'working.format' => 'nullable|in:server,map',
            'working.project_stage' => 'nullable|in:in_development,alpha,beta,released',
            'working.accessibility' => 'nullable|in:public,paid,exclusive,closed',
            'working.ip' => 'nullable|string|max:100',
            'working.version' => 'nullable|string|max:40',
            'working.join_method' => 'nullable|in:direct,network',
            'working.network_name' => 'nullable|string|max:100',
            'working.join_instructions' => 'nullable|string|max:100',
            'working.discord_kind' => 'nullable|in:own,network,none',
            'working.discord_invite' => 'nullable|string|max:200',
            'working.guild_id' => 'nullable|string|max:30',
            'working.discord_server_name' => 'nullable|string|max:120',
            'working.discord_member_count' => 'nullable|integer',
            'working.has_dedicated_announcements' => 'nullable|boolean',
            'working.sections' => 'nullable|array|max:' . self::MAX_SECTIONS,
            'working.sections.*.text' => 'nullable|string|max:2000',
            'working.sections.*.file_ids' => 'nullable|array|max:10',
            'working.sections.*.file_ids.*' => 'integer',
            'working.banner_file_id' => 'nullable|integer',
            'working.links' => 'nullable|array|max:20',
            'working.links.*.label' => 'required|string|max:50',
            'working.links.*.url' => ['required', 'string', 'max:500', 'regex:/^https?:\/\/\S+$/i'],
            'working.staff' => 'nullable|array|max:25',
            'working.staff.*.discord_id' => 'required|string|max:30',
            'working.staff.*.rank' => 'required|in:lead,staff',
            'working.tags' => 'nullable|array',
            'working.tags.primary' => 'nullable|array|max:3',
            'working.tags.primary.*' => 'string',
            'working.tags.secondary' => 'nullable|array',
            'working.tags.secondary.*' => 'string',
            'working.english' => 'nullable|boolean',
        ];
    }

    /** An empty working copy; the applicant is the first team member. */
    public static function blank(string $applicantDiscordId, string $relationship): array
    {
        return [
            'name' => null, 'format' => null, 'project_stage' => null, 'accessibility' => null,
            'ip' => null, 'version' => null, 'join_method' => null, 'network_name' => null, 'join_instructions' => null,
            'discord_kind' => null, 'discord_invite' => null, 'guild_id' => null, 'discord_server_name' => null,
            'discord_member_count' => null, 'has_dedicated_announcements' => null,
            'sections' => [['text' => null, 'file_ids' => []]], 'banner_file_id' => null,
            'links' => [],
            'staff' => [['discord_id' => $applicantDiscordId, 'rank' => $relationship === 'owner' ? 'lead' : 'staff']],
            'tags' => ['primary' => [], 'secondary' => []],
            'english' => null,
        ];
    }

    /** What still blocks submitting, as short human-readable items (empty = complete). */
    public static function missing(array $w): array
    {
        $missing = [];
        $playable = ($w['accessibility'] ?? null) !== null && $w['accessibility'] !== 'closed';

        if (empty($w['name'])) $missing[] = 'name';
        if (empty($w['format'])) $missing[] = 'format (server or map)';
        if (empty($w['project_stage']) || empty($w['accessibility'])) $missing[] = 'status';

        if (($w['format'] ?? null) === 'server' && $playable) {
            if (empty($w['join_method'])) $missing[] = 'how players join';
            if (empty($w['ip'])) $missing[] = ($w['join_method'] ?? null) === 'network' ? 'hub address' : 'server address';
            if (($w['join_method'] ?? null) === 'network' && empty($w['join_instructions'])) $missing[] = 'how to reach the mode from the hub';
        }

        if (empty($w['discord_kind'])) $missing[] = 'Discord';
        elseif ($w['discord_kind'] !== 'none') {
            if (empty($w['guild_id'])) $missing[] = 'a working Discord invite';
            if ($w['discord_kind'] === 'network' && !isset($w['has_dedicated_announcements'])) $missing[] = 'whether the network server has announcements only for this project';
        }

        if (empty(trim($w['sections'][0]['text'] ?? ''))) $missing[] = 'description (section 1 text)';
        if (!collect($w['staff'] ?? [])->contains('rank', 'lead')) $missing[] = 'at least one lead on the team';
        if (empty($w['english'])) $missing[] = 'confirming English is available';

        return $missing;
    }

    /** The project name (channel name): lowercase, hyphens, ASCII. */
    public static function slug(string $name): string
    {
        return Str::slug($name) ?: 'project';
    }

    /** Listed = in the "discovery" list (whose filter mirrors the bot's ProjectRules.isInMainList). */
    public static function isListed(Project $project): bool
    {
        $discovery = ProjectList::where('name', 'discovery')->first();
        return $discovery && FilterEvaluator::matches($project->loadMissing('tags'), $discovery->filters);
    }

    /** Projects this application is about: same Discord server, or same name. */
    public static function matchingProjects(array $w)
    {
        $name = $w['name'] ?? null;
        $guild = $w['guild_id'] ?? null;
        if (!$name && !$guild)
            return collect();

        return Project::query()
            ->where(function ($q) use ($name, $guild) {
                if ($guild) $q->orWhere('guild_id', $guild);
                if ($name) {
                    $q->orWhere('name', self::slug($name))
                        ->orWhereRaw('LOWER(display_name) = ?', [mb_strtolower($name)]);
                }
            })
            ->get();
    }

    /**
     * Reviewer context: a listed project with the same server or name (blocks submitting), an
     * unlisted one (accepting updates it), and earlier rejections of matching applications.
     */
    public static function flags(Application $application): array
    {
        $w = $application->snapshot ?? $application->working;
        $projects = self::matchingProjects($w);
        $listed = $projects->first(fn($p) => self::isListed($p));
        $unlisted = $projects->first(fn($p) => !self::isListed($p));

        $name = $w['name'] ?? null;
        $guild = $w['guild_id'] ?? null;
        $related = Application::query()
            ->where(function ($q) use ($application, $name, $guild) {
                $q->where('id', $application->id);
                if ($guild) $q->orWhere('snapshot->guild_id', $guild);
                if ($name) $q->orWhereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(snapshot, "$.name"))) = ?', [mb_strtolower($name)]);
            })
            ->pluck('id');

        $rejections = ApplicationReview::whereIn('application_id', $related)
            ->whereIn('decision', ApplicationReview::REJECTIONS)
            ->orderBy('id')
            ->get(['application_id', 'revision', 'decision', 'reason', 'created_at']);

        return [
            'listed_project' => $listed ? ['id' => $listed->id, 'name' => $listed->name, 'display_name' => $listed->display_name] : null,
            'unlisted_project' => $unlisted ? ['id' => $unlisted->id, 'name' => $unlisted->name, 'display_name' => $unlisted->display_name] : null,
            'prior_rejections' => $rejections,
        ];
    }
}
