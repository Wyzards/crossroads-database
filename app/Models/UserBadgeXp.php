<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserBadgeXp extends Model
{
    protected $table = 'user_badge_xp';
    protected $with = ['badge'];

    protected $fillable = [
        'user_id',
        'badge_id',
        'total_xp',
        'level',
        'last_calculated_at',
    ];

    protected $casts = [
        'last_calculated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(CrossroadsUser::class);
    }

    public function badge()
    {
        return $this->belongsTo(Badge::class);
    }
}
