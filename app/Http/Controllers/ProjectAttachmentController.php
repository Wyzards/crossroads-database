<?php

namespace App\Http\Controllers;

use ZipArchive;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
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
