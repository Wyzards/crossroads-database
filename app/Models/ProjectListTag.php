<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class ProjectListTag extends Model
{

    protected $fillable = [
        'project_list_id',
        'name',
        'filters',
    ];

    protected $casts = [
        'filters' => 'array',
    ];

    public function projectList()
    {
        return $this->belongsTo(ProjectList::class);
    }
}
