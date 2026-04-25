<?php

namespace App\Http\Controllers;

use App\Models\ProjectList;
use App\FilterValidator;
use Illuminate\Http\Request;

class ProjectListsController extends Controller
{
    public function index()
    {
        return ProjectList::with('entries', 'tags')->get(); // minimal, just the metadata
    }

    public function show(ProjectList $list)
    {
        return response()->json($list->load('entries', 'tags'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'channel_id' => 'nullable|string',
            'filters' => 'required|array',
            'is_active' => 'boolean',
        ]);

        if (!FilterValidator::validate($data['filters']))
        {
            return response()->json(['error' => 'Invalid filter structure'], 422);
        }

        return response()->json(
            ProjectList::create($data)
                ->refresh()
                ->load('entries', 'tags'),
            201
        );
    }

    public function update(Request $request, ProjectList $list)
    {
        $data = $request->validate([
            'name' => 'sometimes|string',
            'channel_id' => 'sometimes|string',
            'filters' => 'sometimes|array',
            'is_active' => 'boolean',
        ]);

        if (isset($data['filters']) && !FilterValidator::validate($data['filters']))
        {
            return response()->json(['error' => 'Invalid filter structure'], 422);
        }

        $list->update($data);

        return response()->json($list->load('entries', 'tags'));
    }

    public function destroy(ProjectList $list)
    {
        $list->delete();
        return response()->json(['success' => true]);
    }
}
