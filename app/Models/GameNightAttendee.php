<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameNightAttendee extends Model
{
    public $timestamps = false;

    protected $fillable = ['game_night_id', 'user_id', 'joined_at', 'rsvp'];

    protected $casts = [
        'joined_at' => 'datetime',
        'rsvp' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(CrossroadsUser::class, 'user_id');
    }

    public function gameNight()
    {
        return $this->belongsTo(GameNight::class);
    }
}
