<?php

namespace App\Services;

use App\Models\BadgeProgression;

class XpService
{
    public function calculateLevel(int $totalXp, ?BadgeProgression $progression): int
    {
        if (!$progression || $totalXp <= 0)
        {
            return 0;
        }

        if ($progression->curve_type === 'linear')
        {
            return (int) floor($totalXp / $progression->base_xp);
        }

        return (int) floor(
            log(($totalXp / $progression->base_xp) + 1, $progression->growth_factor)
        );
    }
}
