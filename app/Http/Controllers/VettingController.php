<?php

namespace App\Http\Controllers;

use App\FilterEvaluator;
use App\Models\CrossroadsUser;
use App\Models\GameNight;
use App\Models\Project;
use App\Models\ProjectList;
use App\Models\Tag;
use App\Models\UserBadgeXp;
use App\Models\UserXpEvent;
use App\Models\VettingCycle;
use App\Models\VettingCycleStep;
use App\Models\VettingSetting;
use App\Services\VettingLease;
use App\Services\XpService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Community Vetting: the cycle record, its steps, the runner's lease and the atomic project
 * transitions. The bot decides (tally, winner); this enforces integrity (preconditions, write-once
 * fields, atomic transitions, the lease fence). Every write takes lease_token and gets 409 unless it
 * is the current lease; the exceptions are PUT settings (the kill switch) and the lease endpoints.
 */
class VettingController extends Controller
{
    private const SETTINGS_PING = [
        'ping_vetting_role_id', 'ping_polls_role_id', 'ping_gamenight_role_id',
        'ping_new_projects_role_id', 'ping_new_archive_role_id',
    ];

    private const STEPS = ['choose', 'hunt_cleanup', 'close', 'poll_close', 'start', 'event', 'announce', 'gn_start', 'gn_end'];

    /** Write-once fields: null -> set, same value -> no-op, different value -> 409. */
    private const WRITE_ONCE = ['chosen_project_id', 'chosen_by', 'announce_message_id', 'poll_thread_id', 'poll_message_id', 'candidates', 'poll_results', 'event_id', 'game_night_id'];

    /** Write-once fields that hold structures: compared by value (key order ignored). */
    private const WRITE_ONCE_JSON = ['candidates', 'poll_results'];

    private function canonical(mixed $v): string
    {
        $sort = function ($x) use (&$sort) {
            if (!is_array($x))
                return $x;
            $x = array_map($sort, $x);
            if (!array_is_list($x))
                ksort($x);
            return $x;
        };
        return json_encode($sort($v));
    }

    private function fence(Request $request): void
    {
        VettingLease::assertHeld($request->input('lease_token'));
    }

    private function conflict(string $message)
    {
        return response()->json(['message' => $message], 409);
    }

    // ---- settings + lease ----

    private function settingsJson(): array
    {
        $s = VettingSetting::row()->fresh();
        return [
            'enabled' => $s->enabled,
            'enabled_at' => $s->enabled_at?->toIso8601String(),
            'hunt_in_poll' => $s->hunt_in_poll,
            'max_candidates' => $s->max_candidates,
            ...$s->only(self::SETTINGS_PING),
            'lease' => VettingLease::holder(), // never the token
        ];
    }

    public function settings()
    {
        return response()->json($this->settingsJson());
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate(['enabled' => 'sometimes|boolean', 'hunt_in_poll' => 'sometimes|boolean', 'max_candidates' => 'sometimes|integer|min:1|max:10']
            + array_fill_keys(self::SETTINGS_PING, 'sometimes|nullable|string|max:30'));

        $s = VettingSetting::row();
        if (array_key_exists('enabled', $data))
        {
            // Only the first enable sets it. A later disable and re-enable is downtime, which the catch-up rules
            // already handle; resetting it would silently skip a Friday group that fell inside the pause.
            if ($data['enabled'] && $s->enabled_at === null)
                $s->enabled_at = now();
            $s->enabled = $data['enabled'];
            unset($data['enabled']);
        }
        $s->fill($data)->save();

        return response()->json($this->settingsJson());
    }

    public function acquireLease(Request $request)
    {
        $data = $request->validate(['holder' => 'required|string|max:255', 'ttl_seconds' => 'required|integer|min:30|max:600']);
        $got = VettingLease::acquire($data['holder'], $data['ttl_seconds']);
        if (!$got)
            return response()->json(VettingLease::holder() ?? ['holder' => null, 'expires_at' => null], 409);

        return response()->json(['token' => $got['token'], 'expires_at' => \Carbon\Carbon::parse($got['expires_at'], 'UTC')->toIso8601String()]);
    }

    public function renewLease(Request $request)
    {
        $data = $request->validate(['token' => 'required|string', 'ttl_seconds' => 'required|integer|min:30|max:600']);
        $expires = VettingLease::renew($data['token'], $data['ttl_seconds']);
        if (!$expires)
            return $this->conflict('The lease is lost');

        return response()->json(['token' => $data['token'], 'expires_at' => \Carbon\Carbon::parse($expires, 'UTC')->toIso8601String()]);
    }

    public function releaseLease(Request $request)
    {
        $data = $request->validate(['token' => 'required|string']);
        VettingLease::release($data['token']);
        return response()->noContent();
    }

    // ---- reads ----

    public function current()
    {
        return response()->json(
            Project::where('community_vetted', 'currently_vetting')->orderBy('community_vetted_at')->orderBy('id')
                ->get(['id', 'name', 'display_name', 'vetting_thread_id', 'community_vetted_at'])
        );
    }

    public function cycles(Request $request)
    {
        $q = VettingCycle::with('steps')->orderByDesc('week_of');
        if ($request->filled('week_of'))
            $q->whereDate('week_of', $request->query('week_of'));
        $limit = max(1, min(50, (int) $request->query('limit', 3)));

        return response()->json($q->limit($limit)->get());
    }

    // ---- cycle writes ----

    private function cycleJson(VettingCycle $cycle)
    {
        return response()->json($cycle->fresh('steps'));
    }

    public function createCycle(Request $request)
    {
        $this->fence($request);
        $data = $request->validate(['week_of' => 'required|date_format:Y-m-d']);

        try
        {
            $cycle = VettingCycle::whereDate('week_of', $data['week_of'])->first()
                ?? VettingCycle::create(['week_of' => $data['week_of']]);
        } catch (UniqueConstraintViolationException)
        {
            $cycle = VettingCycle::whereDate('week_of', $data['week_of'])->firstOrFail();
        }

        return $this->cycleJson($cycle);
    }

    public function updateCycle(Request $request, VettingCycle $cycle)
    {
        $this->fence($request);
        $data = $request->validate([
            'chosen_project_id' => 'sometimes|nullable|integer|exists:projects,id',
            'chosen_by' => 'sometimes|nullable|in:oldest,only,poll,none',
            'announce_message_id' => 'sometimes|nullable|string|max:30',
            'event_id' => 'sometimes|nullable|string|max:30',
            'game_night_id' => 'sometimes|nullable|integer|exists:game_nights,id',
            'poll_thread_id' => 'sometimes|nullable|string|max:30',
            'poll_message_id' => 'sometimes|nullable|string|max:30',
            'candidates' => 'sometimes|nullable|array|max:10',
            'candidates.*.project_id' => 'required|integer',
            'candidates.*.source' => 'required|in:queue,hunt',
            'candidates.*.waiting_since' => 'nullable|string|max:40',
            'candidates.*.answer_id' => 'nullable|integer',
            'poll_results' => 'sometimes|nullable|array|max:10',
            'poll_results.*.project_id' => 'required|integer',
            'poll_results.*.votes' => 'required|integer|min:0',
            'poll_results.*.voter_ids' => 'present|array',
        ]);

        return DB::transaction(function () use ($cycle, $data) {
            $cycle = VettingCycle::whereKey($cycle->id)->lockForUpdate()->firstOrFail();
            $set = [];
            foreach (self::WRITE_ONCE as $field)
            {
                if (!array_key_exists($field, $data) || $data[$field] === null)
                    continue;
                $current = $cycle->{$field};
                $json = in_array($field, self::WRITE_ONCE_JSON, true);
                if ($current === null)
                    $set[$field] = $data[$field];
                elseif ($json ? $this->canonical($current) !== $this->canonical($data[$field]) : (string) $current !== (string) $data[$field])
                    return $this->conflict("$field is already set to a different value");
            }
            if ($set)
                $cycle->update($set);

            return $this->cycleJson($cycle);
        });
    }

    public function stepStatus(Request $request, VettingCycle $cycle, string $step)
    {
        $this->fence($request);
        if (!in_array($step, self::STEPS, true))
            return response()->json(['message' => 'Unknown step'], 422);
        $data = $request->validate([
            'status' => 'required|in:running,done,skipped,failed',
            'note' => 'nullable|string|max:5000',
            'next_attempt_at' => 'nullable|date',
        ]);

        return DB::transaction(function () use ($cycle, $step, $data) {
            $row = VettingCycleStep::where('cycle_id', $cycle->id)->where('step', $step)->lockForUpdate()->first();
            $status = $data['status'];

            if ($row && in_array($row->status, ['done', 'skipped'], true))
            {
                // Terminal is final: the same again is a no-op, anything else is refused.
                return $row->status === $status
                    ? $this->cycleJson($cycle)
                    : $this->conflict("Step $step is already {$row->status}");
            }

            if (!$row)
                $row = new VettingCycleStep(['cycle_id' => $cycle->id, 'step' => $step, 'attempts' => 0]);

            if ($status === 'running')
            {
                $row->attempts = $row->attempts + 1;
                $row->started_at = now();
                $row->finished_at = null;
                $row->next_attempt_at = null;
                $row->note = $data['note'] ?? $row->note;
            }
            else
            {
                $row->finished_at = now();
                $row->next_attempt_at = $status === 'failed' ? ($data['next_attempt_at'] ?? null) : null;
                $row->note = $data['note'] ?? null;
            }
            $row->status = $status;
            $row->save();

            return $this->cycleJson($cycle);
        });
    }

    public function close(Request $request, VettingCycle $cycle)
    {
        $this->fence($request);
        $data = $request->validate([
            'project_id' => 'required|integer|exists:projects,id',
            'thread_id' => 'required|string|max:30',
            'result' => 'required|in:accepted,rejected',
            'up' => 'required|integer|min:0',
            'down' => 'required|integer|min:0',
            'both' => 'required|integer|min:0',
            'votes' => 'required|array',
        ]);

        return DB::transaction(function () use ($cycle, $data) {
            $cycle = VettingCycle::whereKey($cycle->id)->lockForUpdate()->firstOrFail();
            if ($cycle->closed_project_id !== null)
            {
                return $cycle->closed_project_id === $data['project_id']
                    ? $this->cycleJson($cycle)
                    : $this->conflict('This cycle already closed another project');
            }

            $project = Project::whereKey($data['project_id'])->lockForUpdate()->firstOrFail();
            if ($project->community_vetted?->value !== 'currently_vetting')
                return $this->conflict('Project is not currently_vetting');

            $project->community_vetted = $data['result'];
            $project->vetting_thread_id = null;
            $project->save();

            $cycle->update([
                'closed_project_id' => $project->id,
                'close_thread_id' => $data['thread_id'],
                'close_result' => $data['result'],
                'close_up' => $data['up'],
                'close_down' => $data['down'],
                'close_both' => $data['both'],
                'close_votes' => $data['votes'],
                'closed_at' => now(),
            ]);

            return $this->cycleJson($cycle);
        });
    }

    public function start(Request $request, VettingCycle $cycle)
    {
        $this->fence($request);
        $data = $request->validate([
            'project_id' => 'required|integer|exists:projects,id',
            'from' => 'required|in:vetting_queue,project_hunt_queue',
        ]);

        return DB::transaction(function () use ($cycle, $data) {
            $cycle = VettingCycle::whereKey($cycle->id)->lockForUpdate()->firstOrFail();
            if ($cycle->started_project_id !== null)
            {
                return $cycle->started_project_id === $data['project_id']
                    ? $this->cycleJson($cycle)
                    : $this->conflict('This cycle already started another project');
            }

            $project = Project::whereKey($data['project_id'])->lockForUpdate()->firstOrFail();
            if (Project::where('community_vetted', 'currently_vetting')->where('id', '!=', $project->id)->exists())
                return $this->conflict('Another project is currently_vetting');
            if ($project->community_vetted?->value !== $data['from'])
                return $this->conflict("Project is not in {$data['from']}");

            if ($data['from'] === 'project_hunt_queue')
            {
                // Promotion: the project must be public and not disqualified, and it is approved in the same
                // transaction (spec: so a failed vote gets an archives post).
                if ($project->architect_approval?->value === 'disqualified')
                    return $this->conflict('Project is disqualified');
                if ($project->accessibility?->value !== 'public')
                    return $this->conflict('Only public projects are vetted');
                $project->architect_approval = 'approved';
            }

            $project->community_vetted = 'currently_vetting';
            $project->save();

            $cycle->update([
                'started_project_id' => $project->id,
                'started_from' => $data['from'],
                'started_at' => now(),
            ]);

            return $this->cycleJson($cycle);
        });
    }

    public function destroyCycle(Request $request, VettingCycle $cycle)
    {
        $this->fence($request);
        $cycle->delete();
        return response()->noContent();
    }

    // ---- project writes ----

    /** Compare-and-set on vetting_thread_id: written only if the current value equals `expected`. */
    public function setThread(Request $request, Project $project)
    {
        $this->fence($request);
        $data = $request->validate(['thread_id' => 'present|nullable|string|max:30', 'expected' => 'present|nullable|string|max:30']);

        $q = Project::whereKey($project->id);
        $data['expected'] === null ? $q->whereNull('vetting_thread_id') : $q->where('vetting_thread_id', $data['expected']);
        $changed = $q->update(['vetting_thread_id' => $data['thread_id']]);
        $current = Project::whereKey($project->id)->value('vetting_thread_id');

        if (!$changed && $current !== $data['thread_id'])
            return response()->json(['message' => 'vetting_thread_id changed', 'vetting_thread_id' => $current], 409);

        return response()->json(['id' => $project->id, 'vetting_thread_id' => $current]);
    }

    /** Takes a project out of currently_vetting (the manual escape hatch the ProjectController guard removes). */
    public function cancel(Request $request)
    {
        $this->fence($request);
        $data = $request->validate(['project_id' => 'required|integer|exists:projects,id', 'to' => 'required|in:vetting_queue,unvetted']);

        return DB::transaction(function () use ($data) {
            $project = Project::whereKey($data['project_id'])->lockForUpdate()->firstOrFail();
            if ($project->community_vetted?->value !== 'currently_vetting')
                return $this->conflict('Project is not currently_vetting');
            $project->community_vetted = $data['to'];
            $project->save();

            return response()->json($project->load(...Project::RELATIONS));
        });
    }

    // ---- rehearsal cleanup ----

    /**
     * Removes what a rehearsal created (ops.js vetting rehearse). Deliberately narrow: it needs the lease, deletes
     * only context-keyed XP events (never manual or cooldown awards) of ONE user matching the given contexts
     * (exact "kind:id", or "kind:*" for every context of that kind), and only game nights nobody attended.
     * Removed XP is taken back off the badge total and the level recalculated; a total that reaches 0 drops the row.
     */
    public function testCleanup(Request $request, XpService $xpService)
    {
        $this->fence($request);
        $data = $request->validate([
            'discord_id' => 'required_with:contexts|string|max:30',
            'contexts' => 'sometimes|array|max:100',
            'contexts.*' => ['string', 'max:64', 'regex:/^[a-z_]+:(\*|[A-Za-z0-9_-]+)$/'],
            'game_night_ids' => 'sometimes|array|max:20',
            'game_night_ids.*' => 'integer',
        ]);

        return DB::transaction(function () use ($data, $xpService) {
            $xpDeleted = 0;
            $contexts = $data['contexts'] ?? [];
            $user = $contexts ? CrossroadsUser::where('discord_id', $data['discord_id'])->first() : null;
            if ($user)
            {
                $events = UserXpEvent::with('definition.badge')->where('user_id', $user->id)->whereNotNull('context')
                    ->where(function ($q) use ($contexts) {
                        foreach ($contexts as $c)
                        {
                            [$kind, $id] = explode(':', $c, 2);
                            // "kind:*" is a prefix match. Not a string range: the DB's UCA collation doesn't order
                            // punctuation by code point, so ["kind:", "kind;") matched nothing in MariaDB. LIKE here is
                            // only a prefilter ("_" in a kind is a wildcard, and LIKE escaping differs between MariaDB
                            // and SQLite); the exact match is the PHP filter below.
                            $id === '*'
                                ? $q->orWhere('context', 'like', "$kind:%")
                                : $q->orWhere('context', $c);
                        }
                    })->get()
                    ->filter(function ($e) use ($contexts) {
                        foreach ($contexts as $c)
                        {
                            [$kind, $id] = explode(':', $c, 2);
                            if ($id === '*' ? str_starts_with($e->context, "$kind:") : $e->context === $c)
                                return true;
                        }
                        return false;
                    })->values();

                $back = [];
                foreach ($events as $e)
                    $back[$e->definition->badge_id] = ($back[$e->definition->badge_id] ?? 0) + $e->xp_amount;
                UserXpEvent::whereIn('id', $events->pluck('id'))->delete();
                $xpDeleted = $events->count();

                foreach ($back as $badgeId => $amount)
                {
                    $row = UserBadgeXp::with('badge.progression')->where('user_id', $user->id)->where('badge_id', $badgeId)->lockForUpdate()->first();
                    if (!$row)
                        continue;
                    $row->total_xp = max(0, $row->total_xp - $amount);
                    if ($row->total_xp === 0)
                    {
                        $row->delete();
                        continue;
                    }
                    $row->level = max(0, $xpService->calculateLevel($row->total_xp, $row->badge->progression));
                    $row->save();
                }
            }

            $nights = GameNight::whereIn('id', $data['game_night_ids'] ?? [])->withCount('attendees')->get();
            $deletable = $nights->filter(fn($n) => $n->attendees_count === 0);
            GameNight::whereIn('id', $deletable->pluck('id'))->delete();

            return response()->json([
                'xp_events_deleted' => $xpDeleted,
                'game_nights_deleted' => $deletable->count(),
                'game_nights_kept' => $nights->count() - $deletable->count(),
            ]);
        });
    }

    // ---- dry runs ----

    /**
     * Read-only: the names of the lists a hypothetical project would match. Body: optional project_id
     * (start from that project's attributes and real tags), attribute overrides, and optional tags
     * (names; replaces the tags). Nothing is saved.
     */
    public function matchLists(Request $request)
    {
        $data = $request->validate([
            'project_id' => 'nullable|integer|exists:projects,id',
            'architect_approval' => 'nullable|string',
            'community_vetted' => 'nullable|string',
            'accessibility' => 'nullable|string',
            'project_stage' => 'nullable|string',
            'tags' => 'nullable|array',
            'tags.*' => 'string',
        ]);

        $project = !empty($data['project_id']) ? Project::with('tags')->findOrFail($data['project_id']) : new Project();
        foreach (['architect_approval', 'community_vetted', 'accessibility', 'project_stage'] as $f)
            if (!empty($data[$f]))
                $project->{$f} = $data[$f];

        if (isset($data['tags']))
        {
            $project->setRelation('tags', collect($data['tags'])->map(function ($name) {
                $tag = Tag::where('name', $name)->first() ?? new Tag(['name' => $name]);
                $tag->setRelation('pivot', (object) ['position' => null]);
                return $tag;
            }));
        }
        elseif (!$project->relationLoaded('tags'))
            $project->setRelation('tags', collect());

        $names = ProjectList::where('is_active', true)->get()
            ->filter(fn($list) => FilterEvaluator::matches($project, $list->filters))
            ->pluck('name')->values();

        return response()->json($names);
    }
}
