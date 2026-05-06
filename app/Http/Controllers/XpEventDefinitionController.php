<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CrossroadsUser;
use App\Models\XpEventDefinition;
use App\Models\UserXpEvent;
use App\Models\Badge;
use App\Models\UserBadgeXp;
use App\Services\XpService;

class XpEventDefinitionController extends Controller
{
    public function __construct(
        private XpService $xpService
    ) {
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'badge_id' => 'required|exists:badges,id',
            'xp_amount' => 'required|integer|min:0',
            'cooldown_seconds' => 'required|integer|min:0',
        ]);

        $definition = XpEventDefinition::create($validated);

        return response()->json($definition->load('badge'));
    }

    public function update(Request $request, XpEventDefinition $definition)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string',
            'badge_id' => 'sometimes|exists:badges,id',
            'xp_amount' => 'sometimes|integer|min:0',
            'cooldown_seconds' => 'sometimes|integer|min:0',
        ]);

        $definition->update($validated);

        // If XP amount changed → trigger sync
        if (array_key_exists('xp_amount', $validated))
        {
            $this->syncDefinitionAndRecalculate($definition);
        }

        return response()->json($definition->load('badge'));
    }

    public function destroy(XpEventDefinition $definition)
    {
        $definition->delete();

        return response()->json(['success' => true]);
    }

    public function index()
    {
        return XpEventDefinition::with('badge')->get();
    }

    public function show(XpEventDefinition $definition)
    {
        return response()->json($definition);
    }

    private function syncDefinitionAndRecalculate(XpEventDefinition $definition)
    {
        $badgeId = $definition->badge_id;

        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\UserXpEvent> $events */
        $events = UserXpEvent::where('xp_event_definition_id', $definition->id)
            ->with('definition')
            ->get();

        if ($events->isEmpty())
        {
            return;
        }

        $userIds = $events->pluck('user_id')->unique();

        // sync XP values
        foreach ($events as $event)
        {
            $event->xp_amount = $definition->xp_amount;
            $event->save();
        }

        // recalc affected users ONLY
        foreach ($userIds as $userId)
        {
            $this->recalculateUserBadgeXp($userId, $badgeId);
        }
    }

    private function recalculateUserBadgeXp(int $userId, int $badgeId): void
    {
        $badge = Badge::with('progression')->find($badgeId);

        if (!$badge || !$badge->progression)
        {
            return;
        }

        // 🔥 sum ONLY this badge's XP
        $totalXp = UserXpEvent::where('user_id', $userId)
            ->whereHas('definition', fn($q) => $q->where('badge_id', $badgeId))
            ->sum('xp_amount');

        $level = $this->xpService->calculateLevel($totalXp, $badge->progression);

        UserBadgeXp::updateOrCreate(
            [
                'user_id' => $userId,
                'badge_id' => $badgeId,
            ],
            [
                'total_xp' => $totalXp,
                'level' => $level,
                'last_calculated_at' => now(),
            ]
        );
    }
}
