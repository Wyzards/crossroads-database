<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One part of a listing's description: text plus its own attachments, shown in position order. */
class ProjectSection extends Model
{
    protected $fillable = ['project_id', 'position', 'text'];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function attachments()
    {
        return $this->hasMany(ProjectAttachment::class, 'section_id')->orderBy('id');
    }
}
