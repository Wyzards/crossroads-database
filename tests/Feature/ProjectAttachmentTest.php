<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user, 'sanctum');
    }

    public function test_can_list_attachments_for_project()
    {
        $project = Project::factory()->create();
        ProjectAttachment::factory()->count(2)->create(['project_id' => $project->id]);

        $response = $this->getJson("/api/projects/{$project->id}/attachments");

        $response->assertOk()
            ->assertJsonCount(2);
    }

    public function test_can_create_project_attachment()
    {
        $project = Project::factory()->create();
        $response = $this->postJson("/api/projects/{$project->id}/attachments", [
            'path' => "/attachments/example.png",
        ]);

        $response->assertCreated()
            ->assertJsonFragment(['project_id' => $project->id]);
    }

    public function test_can_delete_project_attachment()
    {
        Storage::fake('public');
        $project = Project::factory()->create();
        $attachment = ProjectAttachment::factory()->create(['project_id' => $project->id]);

        $response = $this->deleteJson("/api/projects/{$project->id}/attachments/{$attachment->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('project_attachments', ['id' => $attachment->id]);
    }

    public function test_it_validates_attachment_upload()
    {
        $project = Project::factory()->create();

        $response = $this->postJson("/api/projects/{$project->id}/attachments", [
            // Missing 'path'
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['path']);
    }

    public function test_it_returns_404_when_project_not_found()
    {
        $response = $this->getJson('/api/projects/99999/attachments');
        $response->assertNotFound();

        $response = $this->postJson('/api/projects/99999/attachments', [
            'path' => "/examples/missing.png",
        ]);
        $response->assertNotFound();

        $response = $this->deleteJson('/api/projects/99999/attachments/1');
        $response->assertNotFound();
    }

    public function test_it_returns_404_when_attachment_not_found_for_given_project()
    {
        $project = Project::factory()->create();

        $response = $this->deleteJson("/api/projects/{$project->id}/attachments/99999");

        $response->assertNotFound();
    }
}