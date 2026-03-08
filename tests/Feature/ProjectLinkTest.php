<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class ProjectLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');
    }

    protected function validPayload(array $overrides = []): array
    {
        return array_merge([
            'label' => 'Homepage',
            'url' => 'https://example.com',
        ], $overrides);
    }

    public function test_can_create_project_link(): void
    {
        $project = Project::factory()->create();

        $payload = $this->validPayload();

        $response = $this->postJson("/api/projects/{$project->id}/links", $payload);

        $response->assertCreated()
            ->assertJson(
                fn(AssertableJson $json) =>
                $json->where('project_id', $project->id)
                    ->where('label', $payload['label'])
                    ->where('url', $payload['url'])
                    ->has('id')
                    ->etc()
            );

        $this->assertDatabaseHas('project_links', [
            'project_id' => $project->id,
            'label' => $payload['label'],
            'url' => $payload['url'],
        ]);
    }

    public function test_validation_fails_with_missing_required_fields(): void
    {
        $project = Project::factory()->create();

        $response = $this->postJson("/api/projects/{$project->id}/links", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['label', 'url']);
    }

    public function test_validation_fails_with_invalid_url(): void
    {
        $project = Project::factory()->create();

        $payload = $this->validPayload(['url' => 'not-a-valid-url']);

        $response = $this->postJson("/api/projects/{$project->id}/links", $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['url']);
    }

    public function test_can_list_links_for_project(): void
    {
        $project = Project::factory()->create();
        ProjectLink::factory()->count(3)->create(['project_id' => $project->id]);

        $response = $this->getJson("/api/projects/{$project->id}/links");

        $response->assertOk()
            ->assertJsonCount(3)
            ->assertJson(
                fn(AssertableJson $json) =>
                $json->each(
                    fn($json) =>
                    $json->hasAll(['id', 'project_id', 'label', 'url', 'created_at', 'updated_at'])
                        ->where('project_id', $project->id)
                )
            );
    }

    public function test_list_links_returns_empty_array_if_no_links(): void
    {
        $project = Project::factory()->create();

        $response = $this->getJson("/api/projects/{$project->id}/links");

        $response->assertOk()
            ->assertExactJson([]);
    }

    public function test_list_links_returns_404_if_project_not_found(): void
    {
        $nonExistentProjectId = 9999;

        $response = $this->getJson("/api/projects/{$nonExistentProjectId}/links");

        $response->assertNotFound();
    }

    public function test_can_delete_project_link(): void
    {
        $project = Project::factory()->create();
        $link = ProjectLink::factory()->create(['project_id' => $project->id]);

        $response = $this->deleteJson("/api/projects/{$project->id}/links/{$link->id}");

        $response->assertNoContent();

        $this->assertDatabaseMissing('project_links', ['id' => $link->id]);
    }

    public function test_cannot_delete_link_of_another_project(): void
    {
        $project = Project::factory()->create();
        $otherProject = Project::factory()->create();
        $link = ProjectLink::factory()->create(['project_id' => $otherProject->id]);

        $response = $this->deleteJson("/api/projects/{$project->id}/links/{$link->id}");

        $response->assertNotFound();

        $this->assertDatabaseHas('project_links', ['id' => $link->id]);
    }

    public function test_delete_returns_404_if_link_not_found(): void
    {
        $project = Project::factory()->create();
        $nonExistentLinkId = 9999;

        $response = $this->deleteJson("/api/projects/{$project->id}/links/{$nonExistentLinkId}");

        $response->assertNotFound();
    }
}
