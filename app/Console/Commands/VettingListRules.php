<?php

namespace App\Console\Commands;

use App\FilterEvaluator;
use App\Models\Project;
use App\Models\ProjectList;
use Illuminate\Console\Command;

/**
 * Vetting design, step 2 (listing rules). A data change, like content:migrate-v1:
 *  - discovery: top-level `all` must exclude vetting_queue, currently_vetting and project_hunt_queue
 *    (mirrors the bot's isPubliclyListed); missing conditions are appended, everything else is kept.
 *  - project-hunt-queue: exactly "in the Hunt queue and not disqualified".
 *  - archives: must exclude project_hunt_queue, so an unapproved Hunt project (approval = unapproved
 *    matches archives' `any`) is not shown there as well as in the Hunt list.
 *  - hidden is only checked, never edited: it already excludes project_hunt_queue.
 * Prints the filter JSON before/after and every project whose membership of each list changes.
 * Idempotent. --dry changes nothing.
 */
class VettingListRules extends Command
{
    protected $signature = 'vetting:list-rules {--dry : Report only}';
    protected $description = 'Update discovery / project-hunt-queue / archives list filters for community vetting';

    private const QUEUE_STATES = ['vetting_queue', 'currently_vetting', 'project_hunt_queue'];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');
        $projects = Project::with('tags')->orderBy('id')->get();

        $plans = [
            'discovery' => fn(array $f) => $this->excluding($f, self::QUEUE_STATES),
            'project-hunt-queue' => fn(array $f) => [
                'all' => [
                    ['field' => 'community_vetted', 'operator' => '=', 'value' => 'project_hunt_queue'],
                    ['field' => 'architect_approval', 'operator' => '!=', 'value' => 'disqualified'],
                ],
                'any' => [],
            ],
            'archives' => fn(array $f) => $this->excluding($f, ['project_hunt_queue']),
            'hidden' => fn(array $f) => $f, // check only
        ];

        $changed = 0;
        foreach ($plans as $name => $transform) {
            $list = ProjectList::where('name', $name)->first();
            if (!$list) {
                $this->warn("List '$name' not found; skipped.");
                continue;
            }
            $before = $list->filters ?? ['all' => [], 'any' => []];
            $after = $transform($before);
            $this->line("== $name (id {$list->id})");
            $this->line('before: ' . json_encode($before));
            $this->line('after:  ' . json_encode($after));

            $moves = [];
            foreach ($projects as $p) {
                $was = FilterEvaluator::matches($p, $before);
                $will = FilterEvaluator::matches($p, $after);
                if ($was !== $will) $moves[] = "  #{$p->id} {$p->name}: " . ($will ? 'JOINS' : 'LEAVES');
            }
            $this->line($moves ? implode("\n", $moves) : '  no membership changes');

            if ($before !== $after) {
                $changed++;
                if (!$dry) $list->update(['filters' => $after]);
            }
        }
        $this->info(($dry ? 'Dry run: would update ' : 'Updated ') . "$changed list(s).");
        return 0;
    }

    /** Top-level `all` plus a `community_vetted != x` rule for each missing value; the rest is kept. */
    private function excluding(array $filter, array $values): array
    {
        $filter['all'] = $filter['all'] ?? [];
        $filter['any'] = $filter['any'] ?? [];
        foreach ($values as $v) {
            $has = collect($filter['all'])->contains(fn($r) => ($r['field'] ?? null) === 'community_vetted'
                && ($r['operator'] ?? null) === '!=' && ($r['value'] ?? null) === $v);
            if (!$has) $filter['all'][] = ['field' => 'community_vetted', 'operator' => '!=', 'value' => $v];
        }
        return $filter;
    }
}
