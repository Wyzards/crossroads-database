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

    public function store(Request $request)
    {
        $era = Era::create($request->only([
            'name',
            'started_at',
            'ended_at',
            'is_active'
        ]));

        return response()->json($era, 201);
    }

    public function activate(Era $era)
    {
        // 1. capture current active era
        $previousEra = Era::where('is_active', true)->first();

        // 2. deactivate all
        Era::query()->update(['is_active' => false]);

        // 3. activate selected
        $era->update(['is_active' => true]);

        // 4. decide fallback era for unassigned users
        $fallbackEraId = $previousEra?->id ?? $era->id;

        // 5. assign users with no era
        CrossroadsUser::whereNull('era_id')
            ->update(['era_id' => $fallbackEraId]);

        return response()->json($era);
    }
}
