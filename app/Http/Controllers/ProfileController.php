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
            ->groupBy(fn($ub) => $ub->badge->category);

        $progression = $user->badgeXp;

        return response()->json([
            'user' => $user,
            'era' => $user->era,
            'badges' => $groupedBadges,
            'progression' => $progression,
        ]);
    }
}
