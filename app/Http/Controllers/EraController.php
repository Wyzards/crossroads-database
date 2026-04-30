<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Era;

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
        // deactivate all
        Era::query()->update(['is_active' => false]);

        // activate selected
        $era->update(['is_active' => true]);

        return response()->json($era);
    }
}
