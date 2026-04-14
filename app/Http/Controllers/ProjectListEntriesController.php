<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectListEntry;
use App\Models\ProjectList;
use Illuminate\Http\Request;

class ProjectListEntriesController extends Controller
{

    public function sync(Request $request)
    {
        $data = $request->validate([
            'project_list_id' => 'required|exists:project_lists,id',
            'entries' => 'array',
            'entries.*.project_id' => 'required|exists:projects,id',
            'entries.*.thread_channel_id' => 'required|string',
        ]);

        $listId = $data['project_list_id'];
        $incoming = collect($data['entries']);

        $incomingIds = $incoming->pluck('project_id');

        // 🔴 delete missing
        ProjectListEntry::where('project_list_id', $listId)
            ->whereNotIn('project_id', $incomingIds)
            ->delete();

        // 🟢 upsert current
        foreach ($incoming as $entry)
        {
            ProjectListEntry::updateOrCreate(
                [
                    'project_list_id' => $listId,
                    'project_id' => $entry['project_id'],
                ],
                [
                    'thread_channel_id' => $entry['thread_channel_id'],
                ]
            );
        }

        return response()->json(['success' => true]);
    }

    public function entriesForList(ProjectList $list)
    {
        return response()->json(
            $list->entries()->get()
        );
    }
}
