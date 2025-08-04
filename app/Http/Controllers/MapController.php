<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Models\Map;

class MapController extends Controller
{

    public function show(Map $map)
    {
        return response()->json($map);
    }

    public function index()
    {
        return response()->json(Map::all());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'image_name' => 'required|string|max:255',
            'docker_folder' => 'required|string|max:255',
            'allocated_ram_mb' => 'required|numeric|min:0.1',
            'description' => 'nullable|string',
            'min_players' => 'required|numeric|min:1',
            'max_players' => 'required|numeric'
        ]);

        // Convert GB to MB
        $map = Map::create($validated);

        return response()->json($map, 201);
    }

    public function getByImageName($image_name)
    {
        $map = Map::where('image_name', $image_name)->first();

        if (!$map)
        {
            return response()->json(['message' => 'Map not found'], 404);
        }

        return response()->json($map);
    }

    public function deleteByImageName($image_name)
    {
        $map = Map::where('image_name', $image_name)->first();

        if (!$map)
        {
            return response()->json(['message' => 'Map not found'], 404);
        }

        $map->delete();

        return response()->noContent();
    }

}
