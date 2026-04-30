<?php

namespace App\Models;

use App\Enums\ProgressionCurveType;
use Illuminate\Database\Eloquent\Model;

class BadgeProgression extends Model
{
    protected $table = 'badge_progression';

    protected $fillable = [
        'badge_id',
        'base_xp',
        'growth_factor',
        'curve_type',
    ];

    protected $casts = [
        'curve_type' => ProgressionCurveType::class,
    ];

    public function badge()
    {
        return $this->belongsTo(Badge::class);
    }
}
