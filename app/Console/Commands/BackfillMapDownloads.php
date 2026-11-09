<?php

namespace App\Console\Commands;

use App\Models\Project;
use Illuminate\Console\Command;

/**
 * Fills projects.download_url for maps (format=map) that have none, from their link labelled like
 * "download" (case-insensitive). Reports the maps it couldn't fill, with their links, so a person
 * can decide. --dry changes nothing.
 */
class BackfillMapDownloads extends Command
{
    protected $signature = 'maps:backfill-download {--dry : Report only}';
    protected $description = 'Set download_url on maps from a link labelled "download"';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');
        $maps = Project::withTrashed()->where('format', 'map')->with('links')->orderBy('id')->get();
        $filled = 0;
        foreach ($maps as $map) {
            $label = "#{$map->id} {$map->name}" . ($map->trashed() ? ' (deleted)' : '');
            if ($map->download_url) {
                $this->line("$label: already set ({$map->download_url})");
                continue;
            }
            $link = $map->links->first(fn($l) => stripos($l->label, 'download') !== false && preg_match('/^https?:\/\/\S+$/i', $l->url));
            if (!$link) {
                $this->warn("$label: NO download link. Links: " . ($map->links->map(fn($l) => "{$l->label}={$l->url}")->join(', ') ?: 'none'));
                continue;
            }
            $this->line("$label: download_url <- {$link->url} (from link \"{$link->label}\")");
            if (!$dry) $map->update(['download_url' => $link->url]);
            $filled++;
        }
        $this->info(($dry ? 'Dry run: would fill ' : 'Filled ') . "$filled of {$maps->count()} maps.");
        return 0;
    }
}
