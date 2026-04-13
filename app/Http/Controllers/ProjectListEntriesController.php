<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectListEntry;
use Illuminate\Http\Request;

class ProjectListEntriesController extends Controller
{
    public function entriesForProject(Project $project)
    {
        return ProjectListEntry::with('project', 'projectList')
            ->where('project_id', $project->id)
            ->get();
    }

    public function updateThread(Request $request)
    {
        $request->validate([
            'project_list_id' => 'required|integer|exists:project_lists,id',
            'project_id' => 'required|integer|exists:projects,id',
            'thread_channel_id' => 'required|string|nullable',
        ]);

        $entry = ProjectListEntry::firstOrCreate(
            [
                'project_list_id' => $request->project_list_id,
                'project_id' => $request->project_id,
            ],
            [
                'thread_channel_id' => $request->thread_channel_id,
            ]
        );

        // If it exists but thread_channel_id changed, update
        if ($entry->thread_channel_id !== $request->thread_channel_id)
        {
            $entry->thread_channel_id = $request->thread_channel_id;
            $entry->save();
        }

        return $entry->load('project', 'projectList');
    }
}
