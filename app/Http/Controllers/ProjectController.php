<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Project;

class ProjectController extends Controller
{
    public function index()
    {
        // Load projects with related links, attachments, and staff + users
        $projects = Project::with(['links', 'attachments', 'staff.user'])->get();
        return response()->json($projects);
    }

    public function show(Project $project)
    {
        return response()->json($project->load('links', 'attachments', 'staff.user'));
    }

    public function showByName(string $name)
    {
        $project = Project::where('name', $name)
            ->first();


        if (!$project)
        {
            return response()->json(['message' => 'Project not found'], 404);
        }

        return response()->json($project->load('links', 'attachments', 'staff.user'));
    }

    public function existsByName(string $name)
    {
        $exists = Project::where('name', $name)->exists();

        return response()->json([
            'exists' => $exists,
        ]);
    }


    public function showByGuild(string $guild_id)
    {
        $project = Project::where('guild_id', $guild_id)
            ->first();


        if (!$project)
        {
            return response()->json(['message' => 'Project not found'], 404);
        }

        return response()->json($project->load('links', 'attachments', 'staff.user'));
    }

    public function existsByGuild(string $guildId)
    {
        $exists = Project::where('guild_id', $guildId)->exists();

        return response()->json([
            'exists' => $exists,
        ]);
    }


    public function store(Request $request)
    {
        $validated = $request->validate([
            'channel_id' => 'nullable|string',
            'guild_id' => 'nullable|string',
            'emoji' => 'nullable|string',
            'name' => 'required|string|max:255',
            'display_name' => 'nullable|string|max:255',
            'status' => 'nullable|string',
            'description' => 'nullable|string',
            'ip' => 'nullable|string',
            'role_id' => 'nullable|string',
            'type' => 'nullable|string',
        ]);

        $project = Project::create($validated);
        return response()->json($project, 201);
    }

    public function update(Request $request, Project $project)
    {
        $validated = $request->validate([
            'channel_id' => 'nullable|string',
            'guild_id' => 'nullable|string',
            'emoji' => 'nullable|string',
            'name' => 'sometimes|required|string|max:255',
            'display_name' => 'nullable|string|max:255',
            'status' => 'nullable|string',
            'description' => 'nullable|string',
            'ip' => 'nullable|string',
            'role_id' => 'nullable|string',
            'type' => 'nullable|string',
        ]);

        $project->update($validated);
        return response()->json($project);
    }

    public function destroy(Project $project)
    {
        $project->delete();
        return response()->noContent();
    }
}
