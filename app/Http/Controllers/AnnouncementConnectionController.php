<?php

namespace App\Http\Controllers;

use App\Models\AnnouncementConnection;
use Illuminate\Http\Request;

class AnnouncementConnectionController extends Controller
{
    /** Filters: guild_id, application_id, project_id, status (comma-separated). Newest first. */
    public function index(Request $request)
    {
        $q = AnnouncementConnection::query()->orderByDesc('id');
        foreach (['guild_id', 'application_id', 'project_id'] as $f)
            if ($request->filled($f)) $q->where($f, $request->input($f));
        if ($request->filled('status')) $q->whereIn('status', explode(',', $request->input('status')));
        return response()->json($q->get());
    }

    /** Starts an attempt; any open attempt for the same application/project is cancelled. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'guild_id' => 'required|string|max:30',
            'guild_name' => 'nullable|string|max:120',
            'application_id' => 'nullable|exists:applications,id',
            'project_id' => 'nullable|exists:projects,id',
            'requested_by_discord_id' => 'required|string|max:30',
            'status' => 'sometimes|in:waiting,choosing',
        ]);
        if (empty($data['application_id']) === empty($data['project_id']))
            return response()->json(['message' => 'Give exactly one of application_id or project_id.'], 422);

        $owner = !empty($data['application_id']) ? ['application_id' => $data['application_id']] : ['project_id' => $data['project_id']];
        AnnouncementConnection::where($owner)->whereIn('status', AnnouncementConnection::OPEN)->update(['status' => 'cancelled']);
        return response()->json(AnnouncementConnection::create($data + ['status' => 'waiting']), 201);
    }

    public function update(Request $request, AnnouncementConnection $connection)
    {
        $data = $request->validate([
            'status' => 'sometimes|in:waiting,choosing,done,cancelled',
            'guild_name' => 'sometimes|nullable|string|max:120',
            'followed' => 'sometimes|nullable|array',
            'followed.*.channel_id' => 'required|string',
            'followed.*.name' => 'nullable|string',
            'followed.*.webhook_channel_id' => 'nullable|string',
            'followed.*.webhook_id' => 'nullable|string',
        ]);
        $connection->update($data);
        return response()->json($connection->fresh());
    }
}
