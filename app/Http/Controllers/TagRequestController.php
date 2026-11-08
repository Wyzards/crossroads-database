<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Tag;
use App\Models\TagRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Tag requests. Approving creates the tag (not featured; featuring stays a curation choice) and adds
 * it to the requesting application's tags while it's still editable, in one transaction.
 */
class TagRequestController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'application_id' => 'nullable|exists:applications,id',
            'name' => 'required|string|max:20',
            'requested_by_discord_id' => 'required|string|max:30',
        ]);
        return response()->json(TagRequest::create($data + ['status' => 'pending']), 201);
    }

    public function show(TagRequest $tagRequest)
    {
        return response()->json($tagRequest);
    }

    public function update(Request $request, TagRequest $tagRequest)
    {
        $tagRequest->update($request->validate(['message_id' => 'nullable|string']));
        return response()->json($tagRequest);
    }

    /** Body: {approve: bool, decided_by_discord_id}. 409 if already decided. */
    public function decide(Request $request, TagRequest $tagRequest)
    {
        $data = $request->validate(['approve' => 'required|boolean', 'decided_by_discord_id' => 'required|string']);

        return DB::transaction(function () use ($tagRequest, $data) {
            $req = TagRequest::lockForUpdate()->find($tagRequest->id);
            if ($req->status !== 'pending')
                return response()->json(['message' => "Already {$req->status}."], 409);

            $tag = null;
            if ($data['approve']) {
                $tag = Tag::whereRaw('LOWER(name) = ?', [mb_strtolower($req->name)])->first() ?? Tag::create(['name' => $req->name]);
                $app = $req->application_id ? Application::lockForUpdate()->find($req->application_id) : null;
                if ($app && in_array($app->status, Application::EDITABLE)) {
                    $w = $app->working;
                    $all = array_map('mb_strtolower', array_merge($w['tags']['primary'] ?? [], $w['tags']['secondary'] ?? []));
                    if (!in_array(mb_strtolower($tag->name), $all)) {
                        $w['tags']['secondary'][] = $tag->name;
                        $app->update(['working' => $w]);
                    }
                }
            }
            $req->update([
                'status' => $data['approve'] ? 'approved' : 'rejected',
                'decided_by_discord_id' => $data['decided_by_discord_id'],
                'tag_id' => $tag?->id,
            ]);
            return response()->json($req->fresh());
        });
    }
}
