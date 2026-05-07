<?php

namespace App\Http\Controllers;

use App\Services\XpService;
use App\Models\CrossroadsUser;
use App\Models\XpEventDefinition;
use App\Models\UserXpEvent;
use App\Models\UserBadgeXp;

class XpController extends Controller
{
    public function __construct(
        private XpService $xpService
    ) {
    }

    public function triggerEvent(CrossroadsUser $user, XpEventDefinition $definition)
    {
        // 1. Cooldown check (unchanged)
        $lastEvent = UserXpEvent::where('user_id', $user->id)
            ->where('xp_event_definition_id', $definition->id)
            ->latest('created_at')
            ->first();

        if ($lastEvent && $definition->cooldown_seconds !== 0)
        {
            $diff = now()->diffInSeconds($lastEvent->created_at);

            if ($diff < $definition->cooldown_seconds)
            {
                return response()->json(['message' => 'Cooldown active'], 429);
            }
        }

        // 2. Log event (unchanged)
        $event = UserXpEvent::create([
            'user_id' => $user->id,
            'xp_event_definition_id' => $definition->id,
            'xp_amount' => $definition->xp_amount,
            'created_at' => now(),
        ]);

        // 3. Get badge tied to event
        $badge = $definition->badge;

        // 4. Increment XP atomically
        $userBadgeXp = UserBadgeXp::firstOrCreate(
            [
                'user_id' => $user->id,
                'badge_id' => $badge->id,
            ],
            [
                'total_xp' => 0,
                'level' => 0,
            ]
        );

        $userBadgeXp->total_xp += $definition->xp_amount;

        // 5. Recalculate level ONLY
        $progression = $badge->progression;

        $level = $this->xpService->calculateLevel($userBadgeXp->total_xp, $progression);
        $userBadgeXp->level = max(0, $level);
        $userBadgeXp->last_calculated_at = now();
        $userBadgeXp->save();

        return response()->json($userBadgeXp->load('badge'));
    }
}
