<?php

namespace App\Http\Controllers;

use App\Models\ProjectList;
use App\Models\ProjectListTag;
use App\Models\Project;
use Illuminate\Http\Request;
use App\FilterEvaluator;
use App\FilterValidator;

class ProjectListTagsController extends Controller
{
    // LIST TAGS
    public function index(ProjectList $list)
    {
        return response()->json($list->tags()->get());
    }

    // CREATE TAG
    public function store(ProjectList $list, Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'filters' => 'required|array',
        ]);

        if (!FilterValidator::validate($data['filters']))
        {
            return response()->json(['error' => 'Invalid filter structure'], 422);
        }

        $currentCount = $list->tags()->count();

        if ($currentCount >= 20)
        {
            return response()->json([
                'error' => 'Maximum of 20 tags allowed per project list'
            ], 422);
        }

        $tag = $list->tags()->create($data);

        return response()->json($tag, 201);
    }

    // UPDATE TAG
    public function update(ProjectList $list, ProjectListTag $tag, Request $request)
    {
        if ($tag->project_list_id !== $list->id)
        {
            return response()->json(['error' => 'Tag does not belong to this list'], 403);
        }

        $data = $request->validate([
            'name' => 'sometimes|string',
            'filters' => 'sometimes|array',
        ]);

        if (isset($data['filters']) && !FilterValidator::validate($data['filters']))
        {
            return response()->json(['error' => 'Invalid filter structure'], 422);
        }

        $tag->update($data);

        return response()->json($tag);
    }

    // DELETE TAG
    public function destroy(ProjectList $list, ProjectListTag $tag)
    {
        if ($tag->project_list_id !== $list->id)
        {
            return response()->json(['error' => 'Tag does not belong to this list'], 403);
        }

        $tag->delete();

        return response()->json(['success' => true]);
    }

    public function evaluate(ProjectList $list, Project $project)
    {
        $tags = $list->tags()->get();
        $matched = $this->evaluateTags($tags, $project);

        return response()->json([$matched->values()]);
    }

    public function evaluateBulk(ProjectList $list, Request $request)
    {
        $data = $request->validate([
            'project_ids' => 'required|array',
            'project_ids.*' => 'integer',
        ]);

        $projects = Project::whereIn('id', $data['project_ids'])->get();
        $tags = $list->tags()->get();

        $result = [];

        foreach ($projects as $project)
        {
            $matched = $this->evaluateTags($tags, $project);

            $result[$project->id] = $matched->values();
        }

        return response()->json($result);
    }

    private function evaluateTags($tags, Project $project)
    {
        return $tags
            ->filter(
                fn($tag) =>
                FilterEvaluator::matches($project, $tag->filters)
            )
            ->take(20)
            ->values();
    }
}
