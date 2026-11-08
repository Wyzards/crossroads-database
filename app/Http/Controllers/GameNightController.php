<?php

namespace App\Http\Controllers;

use App\Models\CrossroadsUser;
use App\Models\GameNight;
use App\Models\GameNightAttendee;
use Illuminate\Http\Request;

/**
 * Records game nights and attendance. Awarding XP/badges is done by the bot through the
 * existing XP and badge endpoints, so the usual DMs and logs fire.
 */
class GameNightController extends Controller
{
    private const RELATIONS = ['attendees.user', 'startedBy'];

    /** ?status=ended and/or ?discord_id=<id> (game nights that user attended). */
    public function index(Request $request)
    {
        $query = GameNight::query()->withCount('attendees')->orderByDesc('started_at');

        if ($status = $request->query('status'))
            $query->where('status', $status);

        if ($discordId = $request->query('discord_id'))
            $query->whereHas('attendees.user', fn ($q) => $q->where('discord_id', $discordId));

        return response()->json($query->get());
    }

    /** The running game night; 404 when none is running. */
    public function active()
    {
        $gameNight = GameNight::where('status', 'active')->latest('started_at')->first();

        if (!$gameNight)
            return response()->json(['message' => 'No game night is running'], 404);

        return response()->json($gameNight->load(self::RELATIONS));
    }

    public function show(GameNight $gameNight)
    {
        return response()->json($gameNight->load(self::RELATIONS));
    }

    /**
     * Start a game night (status active; only one at a time) or record a past one
     * (status ended, with started_at/ended_at).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:vetting,hunt',
            'status' => 'sometimes|in:active,ended',
            'started_by_discord_id' => 'nullable|string',
            'channel_id' => 'nullable|string',
            'discord_event_id' => 'nullable|string',
            'discord_event_name' => 'nullable|string|max:255',
            'started_at' => 'nullable|date',
            'ended_at' => 'nullable|date',
        ]);

        $status = $validated['status'] ?? 'active';
        if ($status === 'active' && GameNight::where('status', 'active')->exists())
            return response()->json(['message' => 'A game night is already running'], 409);

        $startedBy = isset($validated['started_by_discord_id'])
            ? CrossroadsUser::firstOrCreate(['discord_id' => $validated['started_by_discord_id']])
            : null;

        $gameNight = GameNight::create([
            'type' => $validated['type'],
            'status' => $status,
            'started_by_user_id' => $startedBy?->id,
            'channel_id' => $validated['channel_id'] ?? null,
            'discord_event_id' => $validated['discord_event_id'] ?? null,
            'discord_event_name' => $validated['discord_event_name'] ?? null,
            'started_at' => $validated['started_at'] ?? now(),
            'ended_at' => $validated['ended_at'] ?? null,
        ]);

        return response()->json($gameNight->load(self::RELATIONS), 201);
    }

    /** End or cancel a running game night. */
    public function update(Request $request, GameNight $gameNight)
    {
        $validated = $request->validate([
            'status' => 'required|in:ended,cancelled',
            'ended_at' => 'nullable|date',
        ]);

        if ($gameNight->status !== 'active')
            return response()->json(['message' => 'Game night is already ' . $gameNight->status], 409);

        $gameNight->update(['status' => $validated['status'], 'ended_at' => $validated['ended_at'] ?? now()]);

        return response()->json($gameNight->load(self::RELATIONS));
    }

    /** Record attendees by Discord id (idempotent; the first join time is kept). */
    public function addAttendees(Request $request, GameNight $gameNight)
    {
        $validated = $request->validate([
            'discord_ids' => 'required|array|min:1',
            'discord_ids.*' => 'string',
        ]);

        foreach (array_unique($validated['discord_ids']) as $discordId) {
            $user = CrossroadsUser::firstOrCreate(['discord_id' => $discordId]);
            GameNightAttendee::firstOrCreate(
                ['game_night_id' => $gameNight->id, 'user_id' => $user->id],
                ['joined_at' => now()]
            );
        }

        return response()->json($gameNight->load(self::RELATIONS));
    }

    /** Mark which attendees RSVP'd (clicked Interested on the Discord event). */
    public function markRsvps(Request $request, GameNight $gameNight)
    {
        $validated = $request->validate([
            'discord_ids' => 'present|array',
            'discord_ids.*' => 'string',
        ]);

        $userIds = CrossroadsUser::whereIn('discord_id', $validated['discord_ids'])->pluck('id');
        $gameNight->attendees()->whereIn('user_id', $userIds)->update(['rsvp' => true]);

        return response()->json($gameNight->load(self::RELATIONS));
    }
}
