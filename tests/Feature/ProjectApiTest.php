<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use App\Models\User;
use App\Models\Project;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_fetch_project()
    {
        // Create user and authenticate with Sanctum token
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        // Create a project
        $project = Project::factory()->create();

        // Make the request
        $response = $this->getJson("/api/projects/{$project->id}");

        // Assert success and expected JSON keys
        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'name',
                'display_name',
                // add other keys you expect
            ]);
    }

    public function test_unauthenticated_user_cannot_access_project()
    {
        $project = Project::factory()->create();

        $response = $this->getJson("/api/projects/{$project->id}");

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }
}
