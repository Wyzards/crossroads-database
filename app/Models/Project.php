<?php

namespace App\Models;

use App\Accessibility;
use App\ArchitectApproval;
use App\CommunityVetted;
use App\Models\ProjectAttachment;
use App\Models\ProjectLink;
use App\ProjectStage;
use App\ProjectTier;
use App\ProjectFormat;
use App\JoinMethod;
use App\DiscordKind;
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
        'ip',
        'role_id',
        'version',
        'discovery_thread_id',
        'architect_approval',
        'community_vetted',
        'project_stage',
        'accessibility',
        'tier',
        'format',
        'banner_attachment_id',
        'join_method',
        'network_name',
        'join_instructions',
        'discord_kind',
        'discord_invite',
        'has_dedicated_announcements',
    ];

    /** Relations every project response carries (the bot renders listings from them). */
    public const RELATIONS = ['links', 'attachments', 'staff.user', 'sections.attachments', 'tags', 'banner'];

    protected $casts = [
        'architect_approval' => ArchitectApproval::class,
        'community_vetted' => CommunityVetted::class,
        'project_stage' => ProjectStage::class,
        'accessibility' => Accessibility::class,
        'tier' => ProjectTier::class,
        'format' => ProjectFormat::class,
        'join_method' => JoinMethod::class,
        'discord_kind' => DiscordKind::class,
        'has_dedicated_announcements' => 'boolean',
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

    public function sections()
    {
        return $this->hasMany(ProjectSection::class)->orderBy('position');
    }

    /** Primary tags (position 1-3) first in order, then secondary tags by name. */
    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'project_tag')
            ->withPivot('position')
            ->withTimestamps()
            ->orderByRaw('project_tag.position is null, project_tag.position, tags.name');
    }

    public function banner()
    {
        return $this->belongsTo(ProjectAttachment::class, 'banner_attachment_id');
    }

    public function listEntries()
    {
        return $this->hasMany(ProjectListEntry::class);
    }
}
