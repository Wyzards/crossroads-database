<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectStaff;
use App\Models\CrossroadsUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class ProjectStaffTest extends TestCase
{
    use RefreshDatabase;

    protected bool $skipAuth = false;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        if (!$this->skipAuth)
        {
            $this->actingAs($user, 'sanctum');
        }
    }

    public function test_can_create_project_staff()
    {
        $project = Project::factory()->create();
        $user = CrossroadsUser::factory()->create();

        $payload = [
            'user_id' => $user->id,
            'rank' => 1,
        ];

        $response = $this->postJson("/api/projects/{$project->id}/staff", $payload);

        $response->assertCreated()
            ->assertJson(
                fn(AssertableJson $json) =>
                $json->where('project_id', $project->id)
                    ->where('user_id', $user->id)
                    ->where('rank', 1)
                    ->etc()
            );

        $this->assertDatabaseHas('project_staff', $payload);
    }

    public function test_can_list_staff_for_a_project()
    {
        $project = Project::factory()->create();
        ProjectStaff::factory()->count(3)->create(['project_id' => $project->id]);

        $response = $this->getJson("/api/projects/{$project->id}/staff");

        $response->assertOk()
            ->assertJsonCount(3);
    }

    public function test_can_delete_project_staff_member()
    {
        $project = Project::factory()->create();
        $user = CrossroadsUser::factory()->create();

        $staff = ProjectStaff::factory()->create([
            'project_id' => $project->id,
            'user_id' => $user->id,
        ]);

        $response = $this->deleteJson("/api/projects/{$project->id}/staff/{$staff->id}");

        $response->assertNoContent();

        $this->assertDatabaseMissing('project_staff', ['id' => $staff->id]);
    }

    public function test_cannot_delete_staff_of_another_project(): void
    {
        $project = Project::factory()->create();
        $otherProject = Project::factory()->create();
        $staff = ProjectStaff::factory()->create(['project_id' => $otherProject->id]);

        $response = $this->deleteJson("/api/projects/{$project->id}/staff/{$staff->id}");

        $response->assertNotFound();

        $this->assertDatabaseHas('project_staff', ['id' => $staff->id]);
    }

    public function test_returns_404_when_deleting_nonexistent_project_staff()
    {
        $project = Project::factory()->create();
        $user = CrossroadsUser::factory()->create();

        $response = $this->deleteJson("/api/projects/{$project->id}/staff/{$user->id}");

        $response->assertStatus(404);
    }

    public function test_returns_404_if_project_does_not_exist()
    {
        $response = $this->postJson("/api/projects/9999/staff", [
            'user_id' => CrossroadsUser::factory()->create()->id,
            'rank' => 1,
        ]);

        $response->assertNotFound();
    }

    public function test_returns_404_if_user_does_not_exist()
    {
        $project = Project::factory()->create();

        $response = $this->postJson("/api/projects/{$project->id}/staff", [
            'user_id' => 9999,
            'rank' => 1,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['user_id']);
    }

    public function test_list_staff_returns_empty_array_if_no_staff(): void
    {
        $project = Project::factory()->create();

        $response = $this->getJson("/api/projects/{$project->id}/staff");

        $response->assertOk()
            ->assertExactJson([]);
    }

    public function test_requires_required_fields_on_store()
    {
        $project = Project::factory()->create();

        $response = $this->postJson("/api/projects/{$project->id}/staff", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }


}
