<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ApplicationReview;
use App\Models\Project;
use App\Services\ApplicationAcceptor;
use App\Services\ApplicationContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Project applications (pipeline v1). Transitions:
 *   draft --submit--> submitted --submit--> submitted (new revision replaces the snapshot)
 *   submitted --accept--> accepted;  --reject(resubmit)--> draft;  --reject(final)--> rejected
 *   draft|submitted --withdraw--> withdrawn
 * Decisions name the revision the reviewer saw; a stale or repeated click gets 409.
 */
class ApplicationController extends Controller
{
    private function respond(Application $application, int $status = 200)
    {
        $application->load('files', 'reviews');
        return response()->json($application->toArray() + [
            'missing' => ApplicationContent::missing($application->working),
            'flags' => ApplicationContent::flags($application),
            'allowed_routes' => $application->snapshot ? ApplicationAcceptor::allowedRoutes($application->snapshot) : [],
        ], $status);
    }

    private function conflict(string $message)
    {
        return response()->json(['message' => $message], 409);
    }

    /** Filters: applicant (Discord id), status (comma-separated), thread_id, review_thread_id. */
    public function index(Request $request)
    {
        $q = Application::query()->orderBy('id');
        if ($request->filled('applicant')) $q->where('applicant_discord_id', $request->input('applicant'));
        if ($request->filled('status')) $q->whereIn('status', explode(',', $request->input('status')));
        if ($request->filled('thread_id')) $q->where('thread_id', $request->input('thread_id'));
        if ($request->filled('review_thread_id')) $q->where('review_thread_id', $request->input('review_thread_id'));
        return response()->json($q->with('reviews')->get());
    }

    public function show(Application $application)
    {
        return $this->respond($application);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'applicant_discord_id' => 'required|string|max:30',
            'relationship' => 'required|in:owner,staff',
        ]);

        $application = Application::create($data + [
            'status' => Application::DRAFT,
            'working' => ApplicationContent::blank($data['applicant_discord_id'], $data['relationship']),
        ]);

        return $this->respond($application, 201);
    }

    /** Discord bookkeeping: the applicant's thread, the card message, the review thread. */
    public function update(Request $request, Application $application)
    {
        $data = $request->validate([
            'thread_id' => 'sometimes|nullable|string',
            'card_message_id' => 'sometimes|nullable|string',
            'review_thread_id' => 'sometimes|nullable|string',
        ]);
        $application->update($data);
        return $this->respond($application);
    }

    /** Replaces the working copy. Allowed until a final decision. */
    public function setWorking(Request $request, Application $application)
    {
        if (!in_array($application->status, Application::EDITABLE))
            return $this->conflict("This application is {$application->status} and can't be edited.");

        $data = $request->validate(ApplicationContent::rules());
        $working = $data['working'];

        // File references must be this application's own files.
        $own = $application->files()->pluck('id')->all();
        $refs = array_merge(...array_map(fn($s) => $s['file_ids'] ?? [], $working['sections'] ?? []));
        if (!empty($working['banner_file_id'])) $refs[] = $working['banner_file_id'];
        if (array_diff($refs, $own))
            return response()->json(['message' => 'Unknown file ids'], 422);

        $application->update(['working' => $working]);
        return $this->respond($application);
    }

    /** Stores one file (image or video, 10 MB). Returns the file row. */
    public function addFile(Request $request, Application $application)
    {
        if (!in_array($application->status, Application::EDITABLE))
            return $this->conflict("This application is {$application->status} and can't be edited.");

        $request->validate(['file' => 'required|file|max:10240|mimetypes:image/png,image/jpeg,image/gif,image/webp,video/mp4,video/webm,video/quicktime']);
        $file = $request->file('file');
        $row = $application->files()->create([
            'path' => $file->store("applications/{$application->id}", 'local'),
            'name' => $file->getClientOriginalName() ?: basename($file->hashName()),
            'content_type' => $file->getMimeType(),
        ]);
        return response()->json($row, 201);
    }

    /** Files as {id, name, data (base64)}; ?ids=1,2 limits them. For previews. */
    public function files(Request $request, Application $application)
    {
        $q = $application->files();
        if ($request->filled('ids')) $q->whereIn('id', explode(',', $request->input('ids')));
        $disk = Storage::disk('local');
        return response()->json($q->get()->map(fn($f) => $disk->exists($f->path)
            ? ['id' => $f->id, 'name' => $f->name, 'data' => base64_encode($disk->get($f->path))]
            : null)->filter()->values());
    }

    /**
     * Submits the working copy: it must be complete and must not duplicate a listed project
     * (whose leads use /myproject). A match with an unlisted project links to it (accepting updates it).
     */
    public function submit(Application $application)
    {
        if (!in_array($application->status, Application::EDITABLE))
            return $this->conflict("This application is {$application->status}.");

        $missing = ApplicationContent::missing($application->working);
        if ($missing)
            return response()->json(['message' => 'Not finished: ' . implode(', ', array_column($missing, 'label')), 'missing' => $missing], 422);

        $matches = ApplicationContent::matchingProjects($application->working);
        if ($listed = $matches->first(fn($p) => ApplicationContent::isListed($p)))
            return response()->json([
                'message' => "{$listed->display_name} is already listed. Its leads can update it with /myproject.",
                'listed_project' => ['id' => $listed->id, 'name' => $listed->name],
            ], 409);
        $unlisted = $matches->first();

        $application->update([
            'status' => Application::SUBMITTED,
            'snapshot' => $application->working,
            'revision' => $application->revision + 1,
            'submitted_at' => now(),
            'project_id' => $unlisted?->id,
        ]);
        return $this->respond($application);
    }

    public function withdraw(Application $application)
    {
        if (!in_array($application->status, Application::EDITABLE))
            return $this->conflict("This application is {$application->status}.");

        $application->update(['status' => Application::WITHDRAWN]);
        Storage::disk('local')->deleteDirectory("applications/{$application->id}");
        $application->files()->delete();
        return $this->respond($application);
    }

    /** Body: {revision, route: watchlist|vetting|hunt, reviewer_discord_id}. */
    public function accept(Request $request, Application $application)
    {
        $data = $request->validate([
            'revision' => 'required|integer',
            'route' => 'required|in:' . implode(',', array_keys(ApplicationAcceptor::ROUTES)),
            'reviewer_discord_id' => 'required|string',
        ]);

        // Lock the row so two reviewers clicking at once can't both decide.
        return DB::transaction(function () use ($application, $data) {
            $application = Application::lockForUpdate()->find($application->id);
            if ($stale = $this->staleDecision($application, $data['revision']))
                return $stale;
            if (!in_array($data['route'], ApplicationAcceptor::allowedRoutes($application->snapshot)))
                return response()->json(['message' => "The {$data['route']} route isn't open to this project: free-to-play projects go to vetting or the Hunt queue, others to the watchlist."], 422);

            [$project, $notes] = ApplicationAcceptor::accept($application, $data['route'], $data['reviewer_discord_id']);
            return response()->json([
                'application' => $application->fresh()->load('reviews'),
                'project' => $project->fresh()->load(Project::RELATIONS),
                'notes' => $notes,
            ]);
        });
    }

    /** Body: {revision, reason, allow_resubmit, reviewer_discord_id}. Resubmit reopens editing; final closes it. */
    public function reject(Request $request, Application $application)
    {
        $data = $request->validate([
            'revision' => 'required|integer',
            'reason' => 'required|string|max:1000',
            'allow_resubmit' => 'required|boolean',
            'reviewer_discord_id' => 'required|string',
        ]);

        return DB::transaction(function () use ($application, $data) {
            $application = Application::lockForUpdate()->find($application->id);
            if ($stale = $this->staleDecision($application, $data['revision']))
                return $stale;

            $application->update(['status' => $data['allow_resubmit'] ? Application::DRAFT : Application::REJECTED]);
            $application->reviews()->create([
                'revision' => $application->revision,
                'decision' => $data['allow_resubmit'] ? ApplicationReview::REJECT_RESUBMIT : ApplicationReview::REJECT_FINAL,
                'reason' => $data['reason'],
                'reviewer_discord_id' => $data['reviewer_discord_id'],
            ]);
            return $this->respond($application);
        });
    }

    private function staleDecision(Application $application, int $revision)
    {
        if ($application->status !== Application::SUBMITTED)
            return $this->conflict("Already decided: this application is {$application->status}.");
        if ($application->revision !== $revision)
            return $this->conflict("The applicant resubmitted (now revision {$application->revision}); review the new version.");
        return null;
    }
}
