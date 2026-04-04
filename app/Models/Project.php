<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\ProjectStatus;
use App\ProjectType;
use App\Models\ProjectLink;
use App\Models\ProjectAttachment;
use App\Models\ProjectStaff;

class Project extends Model
{

    use HasFactory, SoftDeletes;

    protected $fillable = [
        'channel_id',
        'guild_id',
        'emoji',
        'name',
        'display_name',
        'status',
        'description',
        'ip',
        'role_id',
        'type',
        'version'
    ];

    protected $casts = [
        'status' => ProjectStatus::class,
        'type' => ProjectType::class,
    ];

    public function links()
    {
        return $this->hasMany(ProjectLink::class);
    }

    public function attachments()
    {
        return $this->hasMany(ProjectAttachment::class);
    }

    public function staff()
    {
        return $this->hasMany(ProjectStaff::class);
    }
}
