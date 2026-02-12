<?php

namespace App\Http\Controllers;

use ZipArchive;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use App\Models\ProjectAttachment;
use App\Models\Project;
use App\Http\Controllers\Controller;

class ProjectAttachmentController extends Controller
{
    public function index(Project $project)
    {
        return $project->attachments()->get();
    }

    public function store(Request $request, Project $project)
    {
        $validated = $request->validate([
            'files' => ['array'],
            'files.*' => ['file', 'max:10240']
        ]);

        $files = $request->file('files', []);

        $project->attachments()->delete();

        $attachments = [];

        foreach ($files as $file)
        {
            $path = $file->store(
                "projects/{$project->id}/attachments",
                'local'
            );

            $attachments[] = $project->attachments()->create(['path' => $path]);
        }

        return response()->json($attachments, 201);
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

    public function downloadAll(Project $project)
    {
        $attachments = $project->attachments;

        $buffers = [];

        foreach ($attachments as $attachment)
        {
            $filePath = Storage::disk('local')->path($attachment->path);
            if (file_exists($filePath))
            {
                $buffers[] = base64_encode(file_get_contents($filePath));
            }
        }

        return response()->json($buffers);
    }
}
