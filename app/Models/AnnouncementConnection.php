<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One "Connect announcements" attempt for an application or a project (see the migration). */
class AnnouncementConnection extends Model
{
    public const OPEN = ['waiting', 'choosing'];

    protected $fillable = ['guild_id', 'guild_name', 'application_id', 'project_id', 'requested_by_discord_id', 'status', 'followed'];

    protected $casts = ['followed' => 'array'];
}
