<?php

namespace App\Services;

use App\Enums\ProgressionCurveType;
use App\Models\BadgeProgression;

class XpService
{
    public function calculateLevel(int $totalXp, ?BadgeProgression $progression): int
    {
        if (!$progression || $totalXp <= 0)
        {
            return 0;
        }

        if ($progression->curve_type === ProgressionCurveType::LINEAR)
        {
            return (int) floor($totalXp / $progression->base_xp);
        }

        // EXPONENTIAL: XP required increases per level
        $base = $progression->base_xp;
        $growth = $progression->growth_factor;

        $level = 0;
        $remainingXp = $totalXp;

        while (true)
        {
            $xpForNextLevel = (int) floor($base * pow($growth, $level));

            if ($remainingXp < $xpForNextLevel)
            {
                break;
            }

            $remainingXp -= $xpForNextLevel;
            $level++;
        }

        return $level;
    }
}
