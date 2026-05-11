<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class XpEventDefinition extends Model
{

    protected $with = ['badge'];
    protected $fillable = [
        'name',
        'badge_id',
        'xp_amount',
        'cooldown_seconds',
    ];

    public function badge()
    {
        return $this->belongsTo(Badge::class);
    }
}
