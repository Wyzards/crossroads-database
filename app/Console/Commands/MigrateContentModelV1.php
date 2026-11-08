<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\ProjectList;
use App\Models\Tag;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Moves live data into the pipeline v1 content model (feature-audit/PIPELINE_V1_PLAN.md):
 * - format from type (map -> map, else server); type mmo/rpg/smp -> featured tag MMO/RPG/SMP as
 *   primary tag 1; other -> no tag.
 * - Today a project with attachments shows them alone in its starter message: the first one becomes
 *   the banner, any others go to section 1. The description becomes section 1's text.
 * - List filters on type move to format / primary_tags. Discovery's type-based forum tags give way to
 *   the generated featured-tag forum tags ("Other" is dropped: other = no tag).
 * Idempotent (projects that already have sections or a banner are skipped). One transaction;
 * --dry prints the same report and rolls back.
 */
class MigrateContentModelV1 extends Command
{
    protected $signature = 'content:migrate-v1 {--dry : Report only, change nothing}';
    protected $description = 'Move projects and list filters into the v1 content model';

    private const TYPE_TAGS = ['mmo' => 'MMO', 'rpg' => 'RPG', 'smp' => 'SMP'];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');
        $stats = ['projects' => 0, 'skipped' => 0, 'map' => 0, 'tagged' => 0, 'banner' => 0, 'section' => 0, 'section_files' => 0];

        DB::beginTransaction();
        try {
            $tags = [];
            foreach (self::TYPE_TAGS as $type => $name)
                $tags[$type] = Tag::firstOrCreate(['name' => $name], ['featured' => true]);

            foreach (Project::withTrashed()->with('attachments', 'sections', 'tags')->orderBy('id')->get() as $p) {
                $stats['projects']++;
                $type = $p->getRawOriginal('type');
                $line = [];

                $format = $type === 'map' ? 'map' : 'server';
                if ($format === 'map') $stats['map']++;
                $p->format = $format;
                $line[] = "format=$format";

                if (isset($tags[$type]) && !$p->tags->contains('id', $tags[$type]->id)) {
                    $p->tags()->attach($tags[$type]->id, ['position' => 1]);
                    $stats['tagged']++;
                    $line[] = "tag=" . $tags[$type]->name;
                }

                if ($p->sections->isEmpty() && $p->banner_attachment_id === null) {
                    $files = $p->attachments->sortBy('id')->values();
                    if ($files->isNotEmpty()) {
                        $p->banner_attachment_id = $files->first()->id;
                        $stats['banner']++;
                        $line[] = "banner=#" . $files->first()->id;
                    }
                    $rest = $files->slice(1);
                    $text = $p->getRawOriginal('description');
                    if ($text !== null || $rest->isNotEmpty()) {
                        $section = $p->sections()->create(['position' => 1, 'text' => $text]);
                        foreach ($rest as $a)
                            $a->update(['section_id' => $section->id]);
                        $stats['section']++;
                        $stats['section_files'] += $rest->count();
                        $line[] = "section1(text " . mb_strlen($text ?? '') . " chars, files " . $rest->count() . ")";
                    }
                } else {
                    $stats['skipped']++;
                    $line[] = "content already migrated";
                }

                $p->save();
                $this->line(sprintf("#%d %s%s: %s", $p->id, $p->name, $p->trashed() ? " (deleted)" : "", implode(', ', $line)));
            }

            $this->migrateLists();

            $this->info(json_encode($stats));

            if ($dry) {
                DB::rollBack();
                $this->warn('Dry run: rolled back.');
            } else {
                DB::commit();
                $this->info('Committed.');
            }
            return 0;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Failed, rolled back: ' . $e->getMessage());
            return 1;
        }
    }

    private function migrateLists(): void
    {
        foreach (ProjectList::with('tags')->get() as $list) {
            [$filters, $changed] = $this->rewrite($list->filters);
            if ($changed) {
                $list->filters = $filters;
                $this->line("list {$list->name}: filters -> " . json_encode($filters));
            }

            if ($list->name === 'discovery') {
                $list->featured_tags = true;
                $this->line("list discovery: featured_tags on");
            }
            $list->save();

            foreach ($list->tags as $tag) {
                // Gameplay-type forum tags are now generated from featured tags (or dropped, for Other).
                if ($list->featured_tags && $this->onlyGameplayType($tag->filters)) {
                    $this->line("list {$list->name}: forum tag '{$tag->name}' removed (now generated from featured tags or dropped)");
                    $tag->delete();
                    continue;
                }
                [$filters, $changed] = $this->rewrite($tag->filters);
                if ($changed) {
                    $tag->filters = $filters;
                    $tag->save();
                    $this->line("list {$list->name}: forum tag '{$tag->name}' filters -> " . json_encode($filters));
                }
            }
        }
    }

    /** True when every rule is on type with a gameplay value (not map). */
    private function onlyGameplayType(array $filter): bool
    {
        $rules = array_merge($filter['all'] ?? [], $filter['any'] ?? []);
        return $rules !== [] && collect($rules)->every(fn($r) => ($r['field'] ?? null) === 'type' && ($r['value'] ?? null) !== 'map');
    }

    /** Rewrites type rules: map -> format; mmo/rpg/smp -> primary_tags has. Anything else on type aborts. */
    private function rewrite(array $filter): array
    {
        $changed = false;
        foreach (['all', 'any'] as $key) {
            foreach ($filter[$key] ?? [] as $i => $rule) {
                if (isset($rule['all']) || isset($rule['any'])) {
                    [$filter[$key][$i], $c] = $this->rewrite($rule);
                    $changed = $changed || $c;
                    continue;
                }
                if (($rule['field'] ?? null) !== 'type')
                    continue;

                $value = $rule['value'];
                $negated = $rule['operator'] === '!=';
                if ($value === 'map')
                    $filter[$key][$i] = ['field' => 'format', 'operator' => $rule['operator'], 'value' => 'map'];
                elseif (isset(self::TYPE_TAGS[$value]))
                    $filter[$key][$i] = ['field' => 'primary_tags', 'operator' => $negated ? '!has' : 'has', 'value' => self::TYPE_TAGS[$value]];
                else
                    throw new \RuntimeException("No mapping for filter rule " . json_encode($rule));
                $changed = true;
            }
        }
        return [$filter, $changed];
    }
}
