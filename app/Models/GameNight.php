<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameNight extends Model
{
    protected $fillable = [
        'type', 'status', 'started_by_user_id', 'channel_id',
        'discord_event_id', 'discord_event_name', 'started_at', 'ended_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function attendees()
    {
        return $this->hasMany(GameNightAttendee::class);
    }

    public function startedBy()
    {
        return $this->belongsTo(CrossroadsUser::class, 'started_by_user_id');
    }
}
