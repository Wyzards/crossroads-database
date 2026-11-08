<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TagRequest extends Model
{
    protected $fillable = ['application_id', 'name', 'requested_by_discord_id', 'status', 'decided_by_discord_id', 'tag_id', 'message_id'];
}
