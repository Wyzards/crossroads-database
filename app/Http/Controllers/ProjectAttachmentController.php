<?php

namespace App\Http\Controllers;

use ZipArchive;
use Illuminate\Support\Facades\Storage;
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
            'file' => 'required|string|max:10240',
        ]);

        $file = $validated["file"];

        $path = $file->store(
            "projects/{$project->id}/attachments",
            'local'
        );

        $attachment = $project->attachments()->create([
            'path' => $path,
        ]);

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

    public function downloadAll(Project $project)
    {
        $attachments = $project->attachments;

        if ($attachments->isEmpty())
        {
            abort(404);
        }

        $zipName = "project-{$project->id}-attachments.zip";
        $zipPath = storage_path("app/tmp/{$zipName}");

        if (!file_exists(dirname($zipPath)))
        {
            mkdir(dirname($zipPath), 0755, true);
        }

        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($attachments as $attachment)
        {
            $zip->addFile(
                Storage::disk('private')->path($attachment->path),
                basename($attachment->path)
            );
        }

        $zip->close();

        return response()->download($zipPath)->deleteFileAfterSend(true);
    }
}
