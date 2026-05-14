<?php

namespace App\Http\Controllers;

use App\Models\CrossroadsUser;
use App\Models\UserBadgeXp;

use Illuminate\Support\Facades\DB;

class LeaderboardController extends Controller
{
    public function show(string $type)
    {
        return match ($type)
        {
            'hunter' => $this->hunter(),
            'scout' => $this->scout(),
            'total' => $this->total(),
            'badge' => $this->badgeCount(),
            default => response()->json(['error' => 'Invalid leaderboard type'], 400),
        };
    }

    private function hunter()
    {
        return $this->xpLeaderboard('hunter');
    }

    private function scout()
    {
        return $this->xpLeaderboard('scout');
    }

    private function xpLeaderboard(string $badgeKey)
    {
        $rows = UserBadgeXp::query()
            ->whereHas('badge', function ($q) use ($badgeKey) {
                $q->where('key', $badgeKey);
            })
            ->with([
                'badge',
                'user',
                'user.xpEvents' => function ($q) {
                    $q->latest('created_at');
                }
            ])
            ->get()
            ->map(function ($row) {

                $row->last_xp_event_time =
                    $row->user->xpEvents->first()->created_at ?? null;

                return $row;
            })
            ->sort(function ($a, $b) {

                // 1. XP DESC
                if ($a->total_xp !== $b->total_xp)
                {
                    return $b->total_xp <=> $a->total_xp;
                }

                // 2. EARLIER ACHIEVER WINS (older timestamp first)
                return ($a->last_xp_event_time ?? now())
                    <=> ($b->last_xp_event_time ?? now());
            })
            ->values();

        return $this->ranked($rows, fn($r) => $r->total_xp);
    }

    private function total()
    {
        $rows = UserBadgeXp::query()
            ->with('user')
            ->select('user_id', DB::raw('SUM(total_xp) as xp'))
            ->groupBy('user_id')
            ->orderByDesc('xp')
            ->get();

        return $this->ranked($rows, fn($r) => $r->xp);
    }

    private function badgeCount()
    {
        $rows = CrossroadsUser::query()
            ->with([
                'badges' => function ($q) {
                    $q->with('badge');
                }
            ])
            ->get()
            ->map(function ($u) {

                $artifact = 0;
                $achievement = 0;
                $community = 0;

                $latestBadgeTime = null;

                foreach ($u->badges as $ub)
                {

                    $cat = $ub->badge->category;

                    if ($cat === 'artifact')
                        $artifact++;
                    if ($cat === 'achievement')
                        $achievement++;
                    if ($cat === 'community')
                        $community++;

                    if (!$latestBadgeTime || $ub->earned_at > $latestBadgeTime)
                    {
                        $latestBadgeTime = $ub->earned_at;
                    }
                }

                $u->badge_total = $artifact + $achievement + $community;
                $u->last_badge_time = $latestBadgeTime;

                return $u;
            })
            ->sort(function ($a, $b) {

                // primary: badge count DESC
                if ($a->badge_total !== $b->badge_total)
                {
                    return $b->badge_total <=> $a->badge_total;
                }

                // secondary: earlier achiever wins
                return $a->last_badge_time <=> $b->last_badge_time;
            })
            ->values();

        return $this->ranked($rows, fn($r) => $r->badge_total);
    }

    private function ranked($rows, callable $valueFn)
    {
        $ranked = [];
        $rank = 1;
        $prev = null;
        $skip = 0;

        foreach ($rows as $i => $row)
        {
            $value = $valueFn($row);

            if ($prev !== null && $value < $prev)
            {
                $rank += $skip + 1;
                $skip = 0;
            } elseif ($prev !== null && $value === $prev)
            {
                $skip++;
            }

            $ranked[] = [
                'rank' => $rank,
                'value' => $value,
                'user_id' => $row->user_id ?? $row->user->id,
                'discord_id' => $row->user->discord_id ?? null,
            ];

            $prev = $value;
        }

        return response()->json(array_slice($ranked, 0, 10));
    }
}
