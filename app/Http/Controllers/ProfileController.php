<?php

namespace App\Http\Controllers;

use App\Models\CrossroadsUser;

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
            ->map(fn($ub) => $ub->badge)
            ->groupBy('category');

        $progression = $user->badgeXp->map(function ($xp) {
            return [
                'badge_id' => $xp->badge_id,
                'badge_name' => $xp->badge->name,
                'xp' => $xp->total_xp,
                'level' => $xp->level,
            ];
        });

        return response()->json([
            'user' => $user,
            'era' => $user->era,
            'badges' => $groupedBadges,
            'progression' => $progression,
        ]);
    }
}
