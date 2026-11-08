<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A gameplay tag (never stage or accessibility). Featured tags are the forum tag set. */
class Tag extends Model
{
    protected $fillable = ['name', 'featured'];

    protected $casts = ['featured' => 'boolean'];

    public function projects()
    {
        return $this->belongsToMany(Project::class, 'project_tag')->withPivot('position')->withTimestamps();
    }
}
