<?php

namespace App\Http\Controllers;

use App\Enums\ProgressionCurveType;
use App\Models\Badge;
use App\Models\BadgeProgression;
use App\Models\UserBadgeXp;
use App\Services\XpService;
use Illuminate\Http\Request;

class BadgeProgressionController extends Controller
{

    public function __construct(
        private XpService $xpService
    ) {
    }
    public function show(Badge $badge)
    {
        if (!$badge->is_xp_based)
        {
            return response()->json([
                'message' => 'This badge does not use XP progression'
            ], 400);
        }

        $progression = $badge->progression;

        if (!$progression)
        {
            return response()->json(null);
        }

        return response()->json($progression);
    }

    public function update(Request $request, Badge $badge)
    {
        if (!$badge->is_xp_based)
        {
            return response()->json([
                'message' => 'Cannot assign progression to non-XP badge'
            ], 400);
        }

        $validated = $request->validate([
            'curve_type' => ['required', 'string', 'in:' . implode(',', array_column(ProgressionCurveType::cases(), 'value'))],
            'base_xp' => ['required', 'integer', 'min:1'],
            'growth_rate' => ['required', 'numeric', 'min:1'],
        ]);

        $progression = BadgeProgression::updateOrCreate(
            ['badge_id' => $badge->id],
            $validated
        );

        $this->recalculateBadgeProgression($badge);

        return response()->json($progression);
    }

    private function recalculateBadgeProgression(Badge $badge): void
    {
        $progression = $badge->progression;

        if (!$progression)
        {
            return;
        }

        UserBadgeXp::where('badge_id', $badge->id)
            ->chunk(100, function ($rows) use ($progression) {
                foreach ($rows as $userBadgeXp)
                {
                    $userBadgeXp->level = $this->xpService->calculateLevel(
                        $userBadgeXp->total_xp,
                        $progression
                    );

                    $userBadgeXp->last_calculated_at = now();
                    $userBadgeXp->save();
                }
            });
    }


}
