<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Badge;

class BadgeController extends Controller
{
    public function index()
    {
        return response()->json(Badge::all());
    }

    public function store(Request $request)
    {
        $badge = Badge::create($request->only([
            'name',
            'description',
            'category',
            'is_xp_based',
            'rarity',
            'is_active'
        ]));

        return response()->json($badge, 201);
    }

    public function update(Request $request, Badge $badge)
    {
        $badge->update($request->only([
            'name',
            'description',
            'category',
            'is_xp_based',
            'rarity',
            'is_active'
        ]));

        return response()->json($badge);
    }
}