<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Enums\BadgeCategory;
use App\Enums\BadgeRarity;

class Badge extends Model
{
    protected $fillable = [
        'name',
        'description',
        'category',
        'is_xp_based',
        'rarity',
        'is_active',
    ];

    protected $casts = [
        'category' => BadgeCategory::class,
        'rarity' => BadgeRarity::class,
        'is_xp_based' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function userBadges()
    {
        return $this->hasMany(UserBadge::class);
    }

    public function progression()
    {
        return $this->hasOne(BadgeProgression::class);
    }
}
