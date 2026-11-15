<?php

namespace App\Http\Controllers;

use App\Models\CrossroadsUser;
use App\Models\Project;
use App\Services\ApplicationContent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * An admin approved a #project-hunt-suggestions post: it becomes a hidden, public, released server
 * project in the Hunt queue (the hunt-queue list then posts it for votes). The suggester is recorded
 * for the XP at selection and at passing. tier and reviewer are audit only (the bot awards the tier's XP).
 */
class HuntSuggestionController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'thread_id' => 'required|string|max:30',
            'suggester_discord_id' => 'required|string|max:30',
            'tier' => 'required|in:basic,standard,detailed',
            'display_name' => 'required|string|max:255',
            'ip' => 'nullable|string|max:255',
            'discord_invite' => 'nullable|string|max:255',
            'text' => 'nullable|string|max:2000',
            'reviewer_discord_id' => 'required|string|max:30',
        ]);
        $display = trim($data['display_name']);
        $slug = ApplicationContent::slug($display);

        try {
            $project = DB::transaction(function () use ($data, $display, $slug) {
                if (Project::where('suggestion_thread_id', $data['thread_id'])->lockForUpdate()->exists())
                    return 'already approved';
                if (Project::where('name', $slug)->exists())
                    return "a project named $slug already exists";

                $suggester = CrossroadsUser::firstOrCreate(['discord_id' => $data['suggester_discord_id']]);
                $project = Project::create([
                    'name' => ApplicationContent::uniqueName($display),
                    'display_name' => $display,
                    'format' => 'server',
                    'project_stage' => 'released',
                    'accessibility' => 'public',
                    'ip' => $data['ip'] ?? null,
                    'discord_invite' => $data['discord_invite'] ?? null,
                    'architect_approval' => 'hidden',
                    'community_vetted' => 'project_hunt_queue',
                ]);
                $project->forceFill([
                    'suggested_by_user_id' => $suggester->id,
                    'suggestion_thread_id' => $data['thread_id'],
                ])->save();

                $text = trim($data['text'] ?? '');
                if ($text !== '')
                    $project->sections()->create(['position' => 1, 'text' => $text]);
                return $project;
            });
        } catch (UniqueConstraintViolationException) {
            return response()->json(['message' => 'already approved'], 409); // a racing approval of the same post
        }

        if (is_string($project))
            return response()->json(['message' => $project], 409);

        Log::info("Hunt suggestion {$data['thread_id']} ({$data['tier']}) approved by {$data['reviewer_discord_id']} as project {$project->id}");
        return response()->json($project->refresh()->load(...Project::RELATIONS), 201);
    }
}
