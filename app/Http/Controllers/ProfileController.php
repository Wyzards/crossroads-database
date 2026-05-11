<?php

namespace App\Http\Controllers;

use App\Models\CrossroadsUser;
use App\Models\Badge;
use App\Enums\BadgeCategory;

class ProfileController extends Controller
{
    public function show(CrossroadsUser $user)
    {
        $user->load([
            'era',
            'subscription',
            'badges.badge',
            'badgeXp.badge'
        ]);

        $groupedBadges = $user->badges
            ->groupBy(fn($ub) => $ub->badge->category);

        $progression = $user->badgeXp;
        $progressionBadges = Badge::query()
            ->where('category', BadgeCategory::PROGRESSION)
            ->where('is_active', true)
            ->get();

        $lockedAchievements = Badge::query()
            ->where('category', BadgeCategory::ACHIEVEMENT)
            ->where('is_active', true)
            ->whereDoesntHave('userBadges', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->get();

        return response()->json([
            'user' => $user,
            'era' => $user->era,
            'badges' => $groupedBadges,
            'progression' => $progression,
            'locked_achievements' => $lockedAchievements,
            'progression_badges' => $progressionBadges
        ]);
    }
}
