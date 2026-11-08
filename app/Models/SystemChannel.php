<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemChannel extends Model
{
    protected $fillable = [
        'achievements_channel_id',
        'intake_channel_id',
        'intake_review_forum_id',
        'architects_channel_id',
        'hunt_suggestions_channel_id',
    ];
}
