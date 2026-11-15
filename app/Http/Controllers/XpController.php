<?php

namespace App\Http\Controllers;

use App\Services\XpService;
use App\Models\CrossroadsUser;
use App\Models\XpEventDefinition;
use App\Models\UserXpEvent;
use App\Models\UserBadgeXp;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class XpController extends Controller
{
    public function __construct(
        private XpService $xpService
    ) {
    }

    public function triggerEvent(Request $request, CrossroadsUser $user, XpEventDefinition $definition)
    {
        $data = $request->validate([
            'context' => ['nullable', 'string', 'max:64', 'regex:/^[a-z_]+:[A-Za-z0-9_-]+$/'],
        ]);
        $context = $data['context'] ?? null;

        if ($context !== null)
        {
            // Context-keyed award: once per (user, event, context); the cooldown does not apply.
            $exists = UserXpEvent::where('user_id', $user->id)
                ->where('xp_event_definition_id', $definition->id)
                ->where('context', $context)
                ->exists();

            if ($exists)
            {
                return response()->noContent();
            }
        }
        else
        {
            // No context: cooldown check (unchanged)
            $lastEvent = UserXpEvent::where('user_id', $user->id)
                ->where('xp_event_definition_id', $definition->id)
                ->latest('created_at')
                ->first();

            if ($lastEvent && $definition->cooldown_seconds !== 0)
            {
                $diff = now()->timestamp - $lastEvent->created_at->timestamp;

                if ($diff < $definition->cooldown_seconds)
                {
                    return response()->noContent();
                }
            }
        }

        $badge = $definition->badge;

        try
        {
            // Event insert and XP increment commit together, so a retried context-keyed
            // award can never be refused after a half-applied first attempt.
            $userBadgeXp = DB::transaction(function () use ($user, $definition, $badge, $context) {
                // Ensure the row exists, then lock it to serialize concurrent awards.
                UserBadgeXp::firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'badge_id' => $badge->id,
                    ],
                    [
                        'total_xp' => 0,
                        'level' => 0,
                    ]
                );

                $userBadgeXp = UserBadgeXp::where('user_id', $user->id)
                    ->where('badge_id', $badge->id)
                    ->lockForUpdate()
                    ->first();

                // Insert first: a unique violation aborts before any XP is added.
                UserXpEvent::create([
                    'user_id' => $user->id,
                    'xp_event_definition_id' => $definition->id,
                    'xp_amount' => $definition->xp_amount,
                    'context' => $context,
                    'created_at' => now(),
                ]);

                $userBadgeXp->total_xp += $definition->xp_amount;

                // Recalculate level ONLY
                $level = $this->xpService->calculateLevel($userBadgeXp->total_xp, $badge->progression);
                $userBadgeXp->level = max(0, $level);
                $userBadgeXp->last_calculated_at = now();
                $userBadgeXp->save();

                return $userBadgeXp;
            });
        }
        catch (UniqueConstraintViolationException)
        {
            return response()->noContent();
        }

        return response()->json($userBadgeXp->load('badge'));
    }
}
