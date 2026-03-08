<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\ProjectStaff;
use App\ProjectStaffRank;
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

    public function upsert(Project $project, int $user, Request $request)
    {
        $validated = $request->validate([
            'rank' => [
                'required',
                Rule::in(array_map(fn($case) => $case->value, ProjectStaffRank::cases()))
            ]
        ]);

        $staff = $project->staff()->updateOrCreate(
            ['user_id' => $user],
            ['rank' => $validated['rank']]
        );

        $staff->load('user');

        return response()->json(
            $staff,
            $staff->wasRecentlyCreated ? 201 : 200
        );
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
