<?php

namespace App\Http\Controllers;

use App\Models\CrossroadsUser;
use App\Models\UserBadgeXp;
use App\Enums\BadgeCategory;

use Illuminate\Support\Facades\DB;

class LeaderboardController extends Controller
{
    public function show(CrossroadsUser $viewer, string $type)
    {
        return match ($type)
        {
            'hunter' => $this->hunter($viewer),
            'scout' => $this->scout($viewer),
            'total' => $this->total($viewer),
            'badge' => $this->badgeCount($viewer),
            default => response()->json(['error' => 'Invalid leaderboard type'], 400),
        };
    }

    private function hunter(CrossroadsUser $viewer)
    {
        return $this->xpLeaderboard('hunter', $viewer);
    }

    private function scout(CrossroadsUser $viewer)
    {
        return $this->xpLeaderboard('scout', $viewer);
    }

    private function xpLeaderboard(string $badgeKey, CrossroadsUser $viewer)
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

        return $this->ranked($rows, fn($r) => $r->total_xp, $viewer);
    }

    private function total(CrossroadsUser $viewer)
    {
        $rows = UserBadgeXp::query()
            ->with('user')
            ->select('user_id', DB::raw('SUM(total_xp) as xp'))
            ->groupBy('user_id')
            ->orderByDesc('xp')
            ->get();

        return $this->ranked($rows, fn($r) => $r->xp, $viewer);
    }

    private function badgeCount(CrossroadsUser $viewer)
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

                    if ($cat === BadgeCategory::ARTIFACT)
                        $artifact++;
                    if ($cat === BadgeCategory::ACHIEVEMENT)
                        $achievement++;
                    if ($cat === BadgeCategory::COMMUNITY)
                        $community++;

                    if (!$latestBadgeTime || $ub->earned_at > $latestBadgeTime)
                    {
                        $latestBadgeTime = $ub->earned_at;
                    }
                }

                $u->badge_total = $artifact + $achievement + $community;
                $u->last_badge_time = $latestBadgeTime;
                $u->user_id = $u->id;

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

        return $this->ranked($rows, fn($r) => $r->badge_total, $viewer);
    }

    private function ranked($rows, callable $valueFn, ?CrossroadsUser $viewer = null)
    {
        $ranked = [];
        $rank = 1;
        $prev = null;
        $skip = 0;
        $viewerRank = null;

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
                'user_id' => $row->user_id,
                'discord_id' => $row->user?->discord_id ?? $row->discord_id ?? null,
            ];

            if ($viewer && $row->user_id === $viewer->id)
            {
                $viewerRank = $rank;
            }

            $prev = $value;
        }

        $total = count($rows);

        return response()->json([
            'entries' => array_slice($ranked, 0, 10),
            'viewer_rank' => $viewerRank,
            'total' => $total,
        ]);
    }
}
