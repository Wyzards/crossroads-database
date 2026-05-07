<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Era;
use App\Models\CrossroadsUser;

class EraController extends Controller
{
    public function index()
    {
        return response()->json(Era::all());
    }

    public function show(Era $era)
    {
        return response()->json($era);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:eras,name',
            'role_id' => 'nullable|integer',
        ]);

        $era = Era::create([
            'name' => $validated['name'],
            'role_id' => $validated['role_id'] ?? null,
            'is_active' => false,
        ]);

        return response()->json($era);
    }

    public function update(Request $request, Era $era)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|unique:eras,name,' . $era->id,
            'role_id' => 'sometimes|nullable|integer',
        ]);

        $era->update($validated);

        return response()->json($era->fresh());
    }

    public function activate(Era $era)
    {
        $now = now();
        $previousEra = Era::where('is_active', true)->first();

        if ($previousEra)
        {
            $previousEra->update([
                'is_active' => false,
                'ended_at' => $now,
            ]);
        }

        $era->update([
            'is_active' => true,
            'started_at' => $era->started_at ?? $now,
            'ended_at' => null
        ]);

        $fallbackEraId = $previousEra?->id ?? $era->id;

        CrossroadsUser::whereNull('era_id')
            ->update(['era_id' => $fallbackEraId]);

        return response()->json($era);
    }
}
