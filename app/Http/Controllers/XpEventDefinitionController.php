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

    private function syncDefinitionAndRecalculate(XpEventDefinition $definition)
    {
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
            $this->recalculateUserXp($userId);
        }
    }

    private function recalculateUserXp(int $userId): void
    {
        $user = CrossroadsUser::find($userId);

        if (!$user)
            return;

        $badges = Badge::where('is_xp_based', true)->get();

        foreach ($badges as $badge)
        {
            $totalXp = UserXpEvent::where('user_id', $userId)
                ->whereHas('definition', fn($q) => $q->where('badge_id', $badge->id))
                ->sum('xp_amount');

            $level = $this->xpService->calculateLevel($totalXp, $badge->progression);

            UserBadgeXp::updateOrCreate(
                [
                    'user_id' => $userId,
                    'badge_id' => $badge->id,
                ],
                [
                    'total_xp' => $totalXp,
                    'level' => $level,
                    'last_calculated_at' => now(),
                ]
            );
        }
    }
}
