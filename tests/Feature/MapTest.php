<?php

namespace Tests\Feature;

use App\Models\Map;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class MapTest extends TestCase
{
    use RefreshDatabase;

    protected function authenticate(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');
    }

    public function test_can_list_maps(): void
    {
        $this->authenticate();

        Map::factory()->count(3)->create();

        $response = $this->getJson('/api/maps');

        $response->assertOk()
            ->assertJsonCount(3);
    }

    public function test_can_store_map(): void
    {
        $this->authenticate();

        $payload = [
            'name' => 'Test Map',
            'image_name' => 'test-map.png',
            'docker_folder' => 'folder123',
            'allocated_ram_mb' => 2048,
            'description' => 'A cool map',
            'min_players' => 1,
            'max_players' => 10,
        ];

        $response = $this->postJson('/api/maps', $payload);

        $response->assertCreated()
            ->assertJson(
                fn(AssertableJson $json) =>
                $json->where('name', 'Test Map')
                    ->where('image_name', 'test-map.png')
                    ->where('allocated_ram_mb', 2048)
                    ->etc()
            );

        $this->assertDatabaseHas('maps', ['image_name' => 'test-map.png']);
    }

    public function test_it_validates_required_fields_when_creating_a_map(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/maps', []); // empty payload

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'name',
                'image_name',
                'docker_folder',
                'allocated_ram_mb',
                'min_players',
                'max_players',
            ]);
    }

    public function test_can_get_map_by_id(): void
    {
        $this->authenticate();

        $map = Map::factory()->create();

        $response = $this->getJson("/api/maps/{$map->id}");

        $response->assertOk()
            ->assertJson(
                fn(AssertableJson $json) =>
                $json->where('id', $map->id)
                    ->where('name', $map->name)
                    ->etc()
            );
    }

    public function test_can_get_map_by_image_name(): void
    {
        $this->authenticate();

        $map = Map::factory()->create(['image_name' => 'map1.png']);

        $response = $this->getJson("/api/maps/by-image-name/{$map->image_name}");

        $response->assertOk()
            ->assertJson(
                fn(AssertableJson $json) =>
                $json->where('id', $map->id)
                    ->where('image_name', 'map1.png')
                    ->etc()
            );
    }

    public function test_can_delete_map_by_image_name(): void
    {
        $this->authenticate();

        $map = Map::factory()->create(['image_name' => 'map1.png']);

        $response = $this->deleteJson("/api/maps/by-image-name/{$map->image_name}");

        $response->assertNoContent();

        $this->assertDatabaseMissing('maps', ['image_name' => 'map1.png']);
    }

    public function test_it_returns_404_for_missing_map_by_id(): void
    {
        $this->authenticate();

        $response = $this->getJson('/api/maps/99999');

        $response->assertStatus(404);
    }

    public function test_it_returns_404_for_missing_map_by_image_name(): void
    {
        $this->authenticate();

        $response = $this->getJson('/api/maps/by-image-name/nonexistent.png');

        $response->assertStatus(404);
    }
}
