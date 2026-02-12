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
            'files.*' => 'required|file|max:10240',
        ]);

        $project->attachments()->delete();

        $attachments = [];
        foreach ($request->file('files') as $file)
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

        $zipName = "project-{$project->id}-attachments.zip";
        $zipPath = storage_path("app/tmp/{$zipName}");

        // Ensure the tmp directory exists
        if (!file_exists(dirname($zipPath)))
        {
            mkdir(dirname($zipPath), 0755, true);
        }

        // Create the ZIP (even if there are no attachments)
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true)
        {
            return response()->json(['error' => 'Failed to create zip file'], 500);
        }

        foreach ($attachments as $attachment)
        {
            $zip->addFile(
                Storage::disk('local')->path($attachment->path),
                basename($attachment->path)
            );
        }

        $zip->close();

        // Return the ZIP file
        return response()->download($zipPath, $zipName)->deleteFileAfterSend(true);
    }
}
