<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use App\Models\ProjectLink;

class ProjectLinkController extends Controller
{
    public function index(Project $project)
    {
        return $project->links()->get();
    }

    public function store(Request $request, Project $project)
    {
        $validated = $request->validate([
            'url' => 'required|url',
            'label' => 'required|string|max:255',
        ]);

        $link = $project->links()->create($validated);
        return response()->json($link, 201);
    }

    public function destroy(Project $project, ProjectLink $link)
    {
        if ($link->project_id !== $project->id)
        {
            abort(404);
        }

        $link->delete();

        return response()->noContent();
    }
}
