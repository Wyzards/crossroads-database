<?php

namespace App\Models;

use App\Models\ProjectList;
use Illuminate\Database\Eloquent\Model;

class ProjectListEntry extends Model
{
    protected $fillable = [
        'project_list_id',
        'project_id',
        'thread_channel_id',
    ];

    public function projectList()
    {
        return $this->belongsTo(ProjectList::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
