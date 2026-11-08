<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectSection;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * A listing's content beyond its scalar fields: tags, description sections (text + files) and banner.
 * Files live on the API's local disk, since Discord upload links expire.
 */
class ProjectContentController extends Controller
{
    public const MAX_PRIMARY_TAGS = 3;
    public const MAX_SECTION_TEXT = 2000; // one Discord message
    public const MAX_SECTION_FILES = 10;  // one Discord message

    private function fresh(Project $project)
    {
        return response()->json($project->fresh()->load(Project::RELATIONS));
    }

    /** Replaces the project's tags. Body: {primary: [names in order, max 3], secondary: [names]}. Unknown names are refused. */
    public function setTags(Request $request, Project $project)
    {
        $data = $request->validate([
            'primary' => 'present|array|max:' . self::MAX_PRIMARY_TAGS,
            'primary.*' => 'string',
            'secondary' => 'present|array',
            'secondary.*' => 'string',
        ]);

        $names = array_merge($data['primary'], $data['secondary']);
        $tags = Tag::all()->keyBy(fn($t) => mb_strtolower($t->name));
        $unknown = array_values(array_filter($names, fn($n) => !$tags->has(mb_strtolower($n))));
        if ($unknown)
            return response()->json(['message' => 'Unknown tags: ' . implode(', ', $unknown), 'unknown' => $unknown], 422);

        $sync = [];
        foreach ($data['secondary'] as $name)
            $sync[$tags[mb_strtolower($name)]->id] = ['position' => null];
        foreach (array_values($data['primary']) as $i => $name)
            $sync[$tags[mb_strtolower($name)]->id] = ['position' => $i + 1]; // primary wins if listed twice

        $project->tags()->sync($sync);
        return $this->fresh($project);
    }

    public function addSection(Request $request, Project $project)
    {
        $data = $request->validate(['text' => 'nullable|string|max:' . self::MAX_SECTION_TEXT]);
        $position = ($project->sections()->max('position') ?? 0) + 1;
        $project->sections()->create(['position' => $position, 'text' => $data['text'] ?? null]);
        return $this->fresh($project);
    }

    public function updateSection(Request $request, Project $project, ProjectSection $section)
    {
        abort_if($section->project_id !== $project->id, 404);
        $data = $request->validate(['text' => 'nullable|string|max:' . self::MAX_SECTION_TEXT]);
        $section->update(['text' => $data['text'] ?? null]);
        return $this->fresh($project);
    }

    /** Deletes a section and its files, then closes the gap in positions. */
    public function deleteSection(Project $project, ProjectSection $section)
    {
        abort_if($section->project_id !== $project->id, 404);
        DB::transaction(function () use ($project, $section) {
            $this->deleteFiles($section->attachments);
            $section->delete();
            foreach ($project->sections()->get()->values() as $i => $s)
                $s->update(['position' => $i + 1]);
        });
        return $this->fresh($project);
    }

    /** Replaces a section's files (no files clears them). */
    public function setSectionFiles(Request $request, Project $project, ProjectSection $section)
    {
        abort_if($section->project_id !== $project->id, 404);
        $request->validate(['files' => 'sometimes|array|max:' . self::MAX_SECTION_FILES, 'files.*' => 'file|max:10240']);

        DB::transaction(function () use ($request, $project, $section) {
            $this->deleteFiles($section->attachments);
            foreach ($request->file('files', []) as $file)
                $project->attachments()->create(['section_id' => $section->id, 'path' => $this->store($project, $file)]);
        });
        return $this->fresh($project);
    }

    public function setBanner(Request $request, Project $project)
    {
        $request->validate(['file' => 'required|file|image|max:10240']);

        DB::transaction(function () use ($request, $project) {
            $old = $project->banner;
            $attachment = $project->attachments()->create(['path' => $this->store($project, $request->file('file'))]);
            $project->update(['banner_attachment_id' => $attachment->id]);
            if ($old)
                $this->deleteFiles([$old]);
        });
        return $this->fresh($project);
    }

    public function deleteBanner(Project $project)
    {
        if ($project->banner)
            $this->deleteFiles([$project->banner]); // the FK nulls banner_attachment_id
        return $this->fresh($project);
    }

    /** Every attachment as {id, name, data (base64)}, so the bot can place each file where it belongs. */
    public function files(Project $project)
    {
        return response()->json($project->attachments()->get()->map(function ($a) {
            $path = Storage::disk('local')->path($a->path);
            return file_exists($path)
                ? ['id' => $a->id, 'name' => basename($a->path), 'data' => base64_encode(file_get_contents($path))]
                : null;
        })->filter()->values());
    }

    private function store(Project $project, $file): string
    {
        return $file->store("projects/{$project->id}/attachments", 'local');
    }

    private function deleteFiles(iterable $attachments): void
    {
        foreach ($attachments as $a) {
            Storage::disk('local')->delete($a->path);
            $a->delete();
        }
    }
}
