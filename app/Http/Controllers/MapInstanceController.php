<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MapInstance;
use App\Models\CrossroadsUser;

class MapInstanceController extends Controller
{
    // List all instances for the authenticated player (by UUID or user ID)
    public function index(CrossroadsUser $user)
    {
        return response()->json($user->ownedInstances()->get());
    }

    // Create a new map instance owned by the user
    public function store(CrossroadsUser $user, Request $request)
    {
        $validated = $request->validate([
            'map_id' => 'required|integer|exists:maps,id',
            'docker_container_id' => 'nullable|string|max:255',
        ]);

        $instance = MapInstance::create([...$validated, 'owner_id' => $user->id]);

        return response()->json($instance, 201);
    }

    // Show details of a specific instance
    public function show(MapInstance $instance)
    {
        return response()->json($instance);
    }

    // Delete an instance by ID
    public function destroy(MapInstance $instance)
    {
        // TODO: Optionally check if requester is owner
        $instance->delete();

        return response()->noContent();
    }

    // Add a secondary user to an instance (pivot table attach)
    public function addSecondaryUser(MapInstance $instance, Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:crossroads_users,id',
        ]);

        $crossroads_user_id = $validated['user_id'];

        if ($crossroads_user_id === $instance->owner->id)
        {
            return response()->json([
                'message' => 'Cannot add owner as a secondary user.'
            ], 400);
        }

        $exists = $instance->secondaryUsers()->where('crossroads_user_id', $crossroads_user_id)->exists();

        if ($exists)
        {
            return response()->json([
                'message' => 'User is already a secondary user of this instance.'
            ], 400);
        }


        $instance->secondaryUsers()->syncWithoutDetaching($crossroads_user_id);

        return response()->noContent();
    }

    // Remove a secondary user from an instance (pivot detach)
    public function removeSecondaryUser(MapInstance $instance, CrossroadsUser $user)
    {
        $exists = $instance->secondaryUsers()->where('crossroads_user_id', $user->id)->exists();

        if (!$exists)
        {
            return response()->json(['message' => 'User is not a secondary user of this instance.'], 400);
        }

        $instance->secondaryUsers()->detach($user->id);

        return response()->noContent();
    }

    public function getSecondaryUsers(MapInstance $instance)
    {
        return response()->json($instance->secondaryUsers()->get());
    }

}