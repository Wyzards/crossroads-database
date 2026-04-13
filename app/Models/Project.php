<?php

namespace App\Models;

use App\Accessibility;
use App\ArchitectApproval;
use App\CommunityVetted;
use App\Models\ProjectAttachment;
use App\Models\ProjectLink;
use App\ProjectStage;
use App\ProjectType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
        'version',
        'discovery_thread_id',
        'architect_approval',
        'community_vetted',
        'project_stage',
        'accessibility',
    ];

    protected $casts = [
        'architect_approval' => ArchitectApproval::class,
        'community_vetted' => CommunityVetted::class,
        'project_stage' => ProjectStage::class,
        'accessibility' => Accessibility::class,
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

    public function listEntries()
    {
        return $this->hasMany(ProjectListEntry::class);
    }
}
