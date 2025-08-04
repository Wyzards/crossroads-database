<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProjectAttachment;
use App\Models\Project;

class ProjectAttachmentController extends Controller
{
    public function index(Project $project)
    {
        return $project->attachments()->get();
    }

    public function store(Request $request, Project $project)
    {
        $validated = $request->validate([
            'path' => 'required|string',
        ]);

        $attachment = $project->attachments()->create($validated);

        return response()->json($attachment, 201);
    }

    public function destroy(Project $project, ProjectAttachment $attachment)
    {
        if ($attachment->project_id !== $project->id)
        {
            abort(404);
        }

        $attachment->delete();

        return response()->noContent();
    }
}
