<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectList extends Model
{
    protected $fillable = [
        'name',
        'channel_id',
        'filters',
        'featured_tags',
        'is_active',
    ];

    protected $casts = [
        'filters' => 'array', // 👈 IMPORTANT
        'is_active' => 'boolean',
        'featured_tags' => 'boolean',
    ];

    /** Discord allows 20 tags per forum. */
    public const MAX_FORUM_TAGS = 20;

    /**
     * The list's forum tags: its own tags, then (when featured_tags is on) one tag per featured tag,
     * matching projects whose primary tags include it. Generated, not stored, so the featured flag
     * stays the one source; generated tags have id null and featured = true.
     */
    public function forumTags()
    {
        $own = $this->tags()->orderBy('id')->get();

        if (!$this->featured_tags)
            return $own;

        $taken = $own->pluck('name')->map(fn($n) => mb_strtolower($n))->all();
        $generated = Tag::where('featured', true)->orderBy('name')->get()
            ->reject(fn($tag) => in_array(mb_strtolower($tag->name), $taken))
            ->map(function ($tag) {
                $listTag = new ProjectListTag([
                    'project_list_id' => $this->id,
                    'name' => $tag->name,
                    'filters' => ['all' => [['field' => 'primary_tags', 'operator' => 'has', 'value' => $tag->name]], 'any' => []],
                ]);
                $listTag->setAttribute('id', null);
                $listTag->setAttribute('featured', true);
                return $listTag;
            });

        return $own->concat($generated)->take(self::MAX_FORUM_TAGS)->values();
    }

    /** Loads entries and the forum tags (as "tags") for API responses. */
    public function loadForResponse(): static
    {
        $this->load('entries');
        $this->setRelation('tags', $this->forumTags());
        return $this;
    }

    public function tags()
    {
        return $this->hasMany(ProjectListTag::class);
    }

    public function entries()
    {
        return $this->hasMany(ProjectListEntry::class);
    }

    public function projects()
    {
        return $this->belongsToMany(Project::class, 'project_list_entries')
            ->withPivot('thread_channel_id')
            ->withTimestamps();
    }
}
