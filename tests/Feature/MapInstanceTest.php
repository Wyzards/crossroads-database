<?php

namespace Tests\Feature;

use App\Models\CrossroadsUser;
use App\Models\Map;
use App\Models\MapInstance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class MapInstanceTest extends TestCase
{
    use RefreshDatabase;

    protected CrossroadsUser $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');
        $this->owner = CrossroadsUser::factory()->create();
    }

    public function test_can_create_map_instance(): void
    {
        $map = Map::factory()->create();

        $payload = [
            'map_id' => $map->id,
            'docker_container_id' => 'container-123'
        ];

        $response = $this->postJson("/api/map-instances/user/{$this->owner->id}", $payload);

        $response->assertCreated()
            ->assertJson(
                fn(AssertableJson $json) =>
                $json->where('owner_id', (int) $this->owner->id)
                    ->where('map_id', $map->id)
                    ->where('docker_container_id', 'container-123')
                    ->etc()
            );

        $this->assertDatabaseHas('map_instances', $payload);
    }

    public function test_validation_fails_when_required_fields_missing(): void
    {
        $response = $this->postJson("/api/map-instances/user/{$this->owner->id}", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['map_id']);
    }

    public function test_can_list_instances_for_user(): void
    {
        MapInstance::factory()->count(3)->create([
            'owner_id' => $this->owner->id,
        ]);

        $response = $this->getJson("/api/map-instances/user/{$this->owner->id}");

        $response->assertOk()
            ->assertJsonCount(3);
    }

    public function test_can_delete_instance(): void
    {
        $instance = MapInstance::factory()->create();

        $response = $this->deleteJson("/api/map-instances/{$instance->id}");

        $response->assertNoContent();

        $this->assertDatabaseMissing('map_instances', [
            'id' => $instance->id,
        ]);
    }

    public function test_cannot_delete_nonexistent_instance(): void
    {
        $response = $this->deleteJson("/api/map-instances/99999");

        $response->assertStatus(404);
    }

    public function test_can_add_secondary_user(): void
    {
        $instance = MapInstance::factory()->create();
        $user = CrossroadsUser::factory()->create();

        $response = $this->postJson("/api/map-instances/{$instance->id}/secondary-users", [
            'user_id' => $user->id,
        ]);

        $response->assertNoContent();

        $this->assertDatabaseHas('map_instance_secondary_user', [
            'map_instance_id' => $instance->id,
            'crossroads_user_id' => $user->id,
        ]);
    }

    public function test_cannot_add_owner_as_secondary_user(): void
    {
        $instance = MapInstance::factory()->create([
            'owner_id' => $this->owner->id,
        ]);

        $response = $this->postJson("/api/map-instances/{$instance->id}/secondary-users", [
            'user_id' => $this->owner->id,
        ]);

        $response->assertStatus(400)
            ->assertJson(['message' => 'Cannot add owner as a secondary user.']);
    }

    public function test_prevents_duplicate_secondary_users(): void
    {
        $instance = MapInstance::factory()->create();
        $user = CrossroadsUser::factory()->create();

        $this->postJson("/api/map-instances/{$instance->id}/secondary-users", [
            'user_id' => $user->id,
        ])->assertNoContent();

        $response = $this->postJson("/api/map-instances/{$instance->id}/secondary-users", [
            'user_id' => $user->id,
        ]);

        $response->assertStatus(400)
            ->assertJson(['message' => 'User is already a secondary user of this instance.']);
    }

    public function test_can_get_secondary_users(): void
    {
        $instance = MapInstance::factory()->create();
        $user = CrossroadsUser::factory()->create();

        $instance->secondaryUsers()->attach($user->id);

        $response = $this->getJson("/api/map-instances/{$instance->id}/secondary-users");

        $response->assertOk()
            ->assertJsonFragment(['id' => $user->id]);
    }

    public function test_can_remove_secondary_user(): void
    {
        $instance = MapInstance::factory()->create();
        $user = CrossroadsUser::factory()->create();

        $instance->secondaryUsers()->attach($user->id);

        $response = $this->deleteJson("/api/map-instances/{$instance->id}/secondary-users/{$user->id}");

        $response->assertNoContent();

        $this->assertDatabaseMissing('map_instance_secondary_user', [
            'map_instance_id' => $instance->id,
            'crossroads_user_id' => $user->id,
        ]);
    }

    public function test_cannot_remove_secondary_user_if_instance_not_found(): void
    {
        $user = CrossroadsUser::factory()->create();

        $response = $this->deleteJson("/api/map-instances/999/secondary-users/{$user->id}");

        $response->assertStatus(404);
    }

    public function test_cannot_remove_secondary_user_if_user_not_found(): void
    {
        $instance = MapInstance::factory()->create();

        $response = $this->deleteJson("/api/map-instances/{$instance->id}/secondary-users/999");

        $response->assertStatus(404);
    }

    public function test_cannot_remove_user_not_a_secondary(): void
    {
        $instance = MapInstance::factory()->create();
        $user = CrossroadsUser::factory()->create();

        $response = $this->deleteJson("/api/map-instances/{$instance->id}/secondary-users/{$user->id}");

        $response->assertStatus(400)
            ->assertJson(['message' => 'User is not a secondary user of this instance.']);
    }
}
