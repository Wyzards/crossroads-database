<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProjectStaff;
use App\Models\Project;
use App\Models\CrossroadsUser;

class ProjectStaffController extends Controller
{
    public function index($projectId)
    {
        $staff = ProjectStaff::with('user')
            ->where('project_id', $projectId)
            ->get();
        return response()->json($staff);
    }

    public function store(Project $project, Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:crossroads_users,id',
            'rank' => 'nullable|integer',
        ]);

        // prevent duplicate entries for same user/project here
        if ($project->staff()->where('user_id', $validated['user_id'])->exists())
        {
            return response()->json(['message' => 'User already assigned to project'], 409);
        }

        $staff = $project->staff()->create($validated);

        return response()->json($staff, 201);
    }

    public function destroy(Project $project, CrossroadsUser $user)
    {
        $staff = ProjectStaff::where('project_id', $project->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $staff->delete();

        return response()->noContent();
    }
}
