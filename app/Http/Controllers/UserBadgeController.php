<?php

namespace App\Http\Controllers;

use App\Models\CrossroadsUser;
use App\Models\Badge;
use App\Models\UserBadge;

class UserBadgeController extends Controller
{
    public function assignBadge(CrossroadsUser $user, Badge $badge)
    {
        $userBadge = UserBadge::firstOrCreate(
            [
                'user_id' => $user->id,
                'badge_id' => $badge->id,
            ],
            [
                'earned_at' => now(),
            ]
        );

        return response()->json($userBadge->load('badge'));
    }

    public function removeBadge(CrossroadsUser $user, Badge $badge)
    {
        UserBadge::where('user_id', $user->id)
            ->where('badge_id', $badge->id)
            ->delete();

        return response()->json(['success' => true]);
    }
}
