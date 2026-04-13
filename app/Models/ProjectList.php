<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectList extends Model
{
    protected $fillable = [
        'name',
        'channel_id',
        'filters',
        'is_active',
    ];

    protected $casts = [
        'filters' => 'array', // 👈 IMPORTANT
        'is_active' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isActive(): bool
    {
        return $this->is_active;
    }
}
