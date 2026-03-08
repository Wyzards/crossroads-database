<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Models\CrossroadsUser;
use App\ProjectStatus;
use App\ProjectStaffRank;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');
    }

    /**
     * Get a valid payload for creating/updating projects.
     *
     * @param  array  $overrides
     * @return array
     */
    protected function validPayload(array $overrides = []): array
    {
        return array_merge([
            'channel_id' => 'ABCDEF123456789012',
            'guild_id' => 'ZYXWVUT987654321098',
            'emoji' => '🎮',
            'name' => 'My Project',
            'display_name' => 'My Display Name',
            'status' => ProjectStatus::IN_DEVELOPMENT->value, // controller expects string; model casts to enum
            'description' => 'A project description.',
            'ip' => '127.0.0.1',
            'role_id' => '123456789012345',
            'type' => 'mmo', // controller expects string; model casts to enum
        ], $overrides);
    }

    public function test_index_returns_projects_with_relationships()
    {
        // Create projects with relationships
        Project::factory()->count(2)->create()->each(function ($project) {
            $project->links()->create([
                'url' => 'https://example.com',
                'label' => 'Example Link'
            ]);
            $project->attachments()->create([
                'filename' => 'file.txt',
                'path' => '/files/file.txt',
            ]);
            $project->staff()->create([
                'user_id' => CrossroadsUser::factory()->create()->id,
                'rank' => ProjectStaffRank::STAFF->value,
            ]);
        });

        $response = $this->getJson('/api/projects');

        $response->assertOk()
            ->assertJsonCount(2)
            ->assertJson(
                fn(AssertableJson $json) =>
                $json->each(
                    fn($project) =>
                    $project->has('links')
                        ->has('attachments')
                        ->has('staff')
                        ->etc()
                )
            );
    }

    public function test_show_returns_project_with_relationships()
    {
        $project = Project::factory()->create();
        $project->links()->create([
            'url' => 'https://example.com',
            'label' => 'Example Link',
        ]);
        $project->attachments()->create([
            'filename' => 'file.txt',
            'path' => '/files/file.txt',
        ]);
        $project->staff()->create([
            'user_id' => CrossroadsUser::factory()->create()->id,
            'rank' => ProjectStaffRank::STAFF->value,
        ]);

        $response = $this->getJson("/api/projects/{$project->id}");

        $response->assertOk()
            ->assertJson(
                fn(AssertableJson $json) =>
                $json->where('id', $project->id)
                    ->where('name', $project->name)
                    ->has('links', 1)
                    ->has('attachments', 1)
                    ->has('staff', 1)
                    ->etc()
            );
    }

    public function test_show_returns_404_if_not_found()
    {
        $nonExistentId = 999999;

        $this->getJson("/api/projects/{$nonExistentId}")
            ->assertNotFound();
    }

    public function test_showByName_returns_project_with_relationships()
    {
        $project = Project::factory()->create([
            'name' => 'TestProject',
            'display_name' => 'Display Test'
        ]);

        // Add relationships
        $project->links()->create([
            'url' => 'https://example.com',
            'label' => 'Example Link'
        ]);
        $project->attachments()->create([
            'filename' => 'file.txt',
            'path' => '/files/file.txt',
        ]);
        $project->staff()->create([
            'user_id' => CrossroadsUser::factory()->create()->id,
            'rank' => ProjectStaffRank::STAFF->value,
        ]);

        // Test retrieval by "name"
        $response = $this->getJson("/api/projects/by-name/{$project->name}");

        $response->assertOk()
            ->assertJson(
                fn(AssertableJson $json) =>
                $json->where('id', $project->id)
                    ->where('name', $project->name)
                    ->has('links', 1)
                    ->has('attachments', 1)
                    ->has('staff', 1)
                    ->etc()
            );
    }

    public function test_showByName_returns_404_if_project_not_found()
    {
        $nonExistentName = 'NoSuchProject';

        $response = $this->getJson("/api/projects/by-name/{$nonExistentName}");

        $response->assertNotFound()
            ->assertJson([
                'message' => 'Project not found',
            ]);
    }

    public function test_exists_by_name_returns_true_when_project_exists()
    {
        $project = Project::factory()->create([
            'name' => 'test-project',
        ]);

        $response = $this->getJson("/api/projects/exists/by-name/{$project->name}");

        $response->assertOk()
            ->assertJson([
                'exists' => true,
            ]);
    }

    public function test_exists_by_name_returns_false_when_project_does_not_exist()
    {
        $response = $this->getJson('/api/projects/exists/by-name/non-existent-project');

        $response->assertOk()
            ->assertJson([
                'exists' => false,
            ]);
    }

    public function test_show_by_guild_returns_project_with_relationships()
    {
        $project = Project::factory()->create([
            'guild_id' => 'GUILD123',
        ]);

        $project->links()->create([
            'url' => 'https://example.com',
            'label' => 'Example Link',
        ]);

        $project->attachments()->create([
            'filename' => 'file.txt',
            'path' => '/files/file.txt',
        ]);

        $project->staff()->create([
            'user_id' => CrossroadsUser::factory()->create()->id,
            'rank' => \App\ProjectStaffRank::STAFF->value,
        ]);

        $response = $this->getJson("/api/projects/by-guild/{$project->guild_id}");

        $response->assertOk()
            ->assertJson(
                fn(AssertableJson $json) =>
                $json->where('id', $project->id)
                    ->where('guild_id', 'GUILD123')
                    ->has('links', 1)
                    ->has('attachments', 1)
                    ->has('staff', 1)
                    ->etc()
            );
    }

    public function test_show_by_guild_returns_404_when_project_not_found()
    {
        $response = $this->getJson('/api/projects/by-guild/GUILD_DOES_NOT_EXIST');

        $response->assertNotFound()
            ->assertJson([
                'message' => 'Project not found',
            ]);
    }


    public function test_exists_by_guild_returns_true_when_project_exists()
    {
        $project = Project::factory()->create([
            'guild_id' => 'GUILD123',
        ]);

        $response = $this->getJson("/api/projects/exists/by-guild/{$project->guild_id}");

        $response->assertOk()
            ->assertJson([
                'exists' => true,
            ]);
    }

    public function test_exists_by_guild_returns_false_when_project_does_not_exist()
    {
        $response = $this->getJson('/api/projects/exists/by-guild/GUILD_DOES_NOT_EXIST');

        $response->assertOk()
            ->assertJson([
                'exists' => false,
            ]);
    }


    public function test_store_creates_project_with_valid_data()
    {
        $payload = $this->validPayload();

        $response = $this->postJson('/api/projects', $payload);

        $response->assertCreated()
            ->assertJson(
                fn(AssertableJson $json) =>
                $json->where('name', $payload['name'])
                    ->where('guild_id', $payload['guild_id'])
                    ->where('status', $payload['status'])
                    ->where('type', $payload['type'])
                    ->etc()
            );

        $this->assertDatabaseHas('projects', [
            'name' => $payload['name'],
            'guild_id' => $payload['guild_id'],
        ]);
    }

    public function test_store_allows_nullable_fields()
    {
        $payload = $this->validPayload([
            'channel_id' => null,
            'emoji' => null,
            'display_name' => null,
            'status' => null,
            'description' => null,
            'ip' => null,
            'role_id' => null,
            'type' => null,
        ]);

        $response = $this->postJson('/api/projects', $payload);

        $response->assertCreated()
            ->assertJson(
                fn(AssertableJson $json) =>
                $json->where('name', $payload['name'])
                    ->where('guild_id', $payload['guild_id'])
                    ->where('channel_id', null)
                    ->where('emoji', null)
                    ->where('display_name', null)
                    ->where('status', null)
                    ->where('description', null)
                    ->where('ip', null)
                    ->where('role_id', null)
                    ->where('type', null)
                    ->etc()
            );

        $this->assertDatabaseHas('projects', [
            'name' => $payload['name'],
            'guild_id' => $payload['guild_id'],
            'channel_id' => null,
        ]);
    }

    public function test_store_requires_name()
    {
        $payload = $this->validPayload(['name' => null]);

        $response = $this->postJson('/api/projects', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    // TODO: Validate project name alphanumeric dashed (discord channel format)

    public function test_update_modifies_project_with_valid_data()
    {
        $project = Project::factory()->create(['name' => 'Old Name']);

        $payload = ['name' => 'New Name', 'status' => 'playable'];

        $response = $this->putJson("/api/projects/{$project->id}", $payload);

        $response->assertOk()
            ->assertJson(
                fn(AssertableJson $json) =>
                $json->where('id', $project->id)
                    ->where('name', 'New Name')
                    ->where('status', 'playable')
                    ->etc()
            );

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'New Name',
        ]);
    }

    public function test_update_allows_partial_data_and_nullable_fields()
    {
        $project = Project::factory()->create([
            'channel_id' => 'OLDCHANNEL',
            'emoji' => '😀',
            'display_name' => 'Old Display',
            'status' => 'playable',
            'description' => 'Old desc',
            'ip' => '192.168.1.1',
            'role_id' => 'ROLEOLD123',
            'type' => 'rpg',
        ]);

        $payload = [
            'channel_id' => null,
            'emoji' => null,
            'display_name' => null,
            'description' => null,
            'ip' => null,
            'role_id' => null,
            'type' => null,
        ];

        $response = $this->putJson("/api/projects/{$project->id}", $payload);

        $response->assertOk()
            ->assertJson(
                fn(AssertableJson $json) =>
                $json->where('id', $project->id)
                    ->where('channel_id', null)
                    ->where('emoji', null)
                    ->where('display_name', null)
                    ->where('description', null)
                    ->where('ip', null)
                    ->where('role_id', null)
                    ->where('type', null)
                    ->etc()
            );

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'channel_id' => null,
            'emoji' => null,
            'display_name' => null,
        ]);
    }

    public function test_update_requires_name_when_present()
    {
        $project = Project::factory()->create();

        // name present but null = error
        $payload = ['name' => null];

        $response = $this->putJson("/api/projects/{$project->id}", $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_update_returns_404_if_not_found()
    {
        $nonExistentId = 999999;

        $response = $this->putJson("/api/projects/{$nonExistentId}", ['name' => 'New Name']);

        $response->assertNotFound();
    }

    public function test_destroy_deletes_project()
    {
        $project = Project::factory()->create();

        $response = $this->deleteJson("/api/projects/{$project->id}");

        $response->assertNoContent();

        $this->assertSoftDeleted('projects', ['id' => $project->id]);
    }

    public function test_destroy_returns_404_if_not_found()
    {
        $nonExistentId = 999999;

        $response = $this->deleteJson("/api/projects/{$nonExistentId}");

        $response->assertNotFound();
    }
}
