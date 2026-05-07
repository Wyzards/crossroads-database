<?php

namespace App\Http\Controllers;

use App\Models\CrossroadsUser;
use App\Models\Era;
use Illuminate\Http\Request;

class CrossroadsUserController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'minecraft_uuid' => 'nullable|string|unique:crossroads_users,minecraft_uuid',
            'discord_id' => 'nullable|string|unique:crossroads_users,discord_id',
        ]);

        // Ensure at least one identifier is provided
        if (empty($validated['minecraft_uuid']) && empty($validated['discord_id']))
        {
            return response()->json([
                'message' => 'Either minecraft_uuid or discord_id must be provided.'
        ], 422);
        }

        $activeEra = Era::where('is_active', true)->first();

        $user = CrossroadsUser::create([
            ...$validated,
            'era_id' => $activeEra?->id,
        ]);

        return response()->json($user->load('era'), 201);
    }

    public function show(CrossroadsUser $user)
    {
        return response()->json($user->load('era'));
    }

    public function index()
    {
        return response()->json(CrossroadsUser::with('era')->get());
    }

    public function destroy(CrossroadsUser $user)
    {
        $user->delete();

        return response()->noContent();
    }

    public function findByMinecraftUuid($uuid)
    {
        $user = CrossroadsUser::where('minecraft_uuid', $uuid)->first();
        if (!$user)
        {
            return response()->json(['message' => 'User not found'], 404);
        }
        return response()->json($user->load('era'));
    }

    public function findByDiscordId($discordId)
    {
        $user = CrossroadsUser::where('discord_id', $discordId)->first();
        if (!$user)
        {
            return response()->json(['message' => 'User not found'], 404);
        }
        return response()->json($user->load('era'));
    }

}
