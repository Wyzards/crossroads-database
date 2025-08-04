<?php

namespace Tests\Feature;

use App\Models\CrossroadsUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class CrossroadsUserTest extends TestCase
{
    use RefreshDatabase;

    protected function authenticate(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');
    }

    public function test_can_create_crossroads_user(): void
    {
        $this->authenticate();

        $payload = [
            'minecraft_uuid' => 'uuid-123',
            'discord_id' => '123456789',
        ];

        $response = $this->postJson('/api/crossroads-users', $payload);

        $response->assertCreated()
            ->assertJson(
                fn(AssertableJson $json) =>
                $json->where('minecraft_uuid', 'uuid-123')
                    ->where('discord_id', '123456789')
                    ->etc()
            );

        $this->assertDatabaseHas('crossroads_users', $payload);
    }

    public function test_can_list_crossroads_users(): void
    {
        $this->authenticate();

        CrossroadsUser::factory()->count(3)->create();

        $response = $this->getJson('/api/crossroads-users');

        $response->assertOk()
            ->assertJsonCount(3);
    }

    public function test_can_show_crossroads_user(): void
    {
        $this->authenticate();

        $user = CrossroadsUser::factory()->create();

        $response = $this->getJson("/api/crossroads-users/{$user->id}");

        $response->assertOk()
            ->assertJson(
                fn(AssertableJson $json) =>
                $json->where('id', $user->id)
                    ->where('minecraft_uuid', $user->minecraft_uuid)
                    ->where('discord_id', $user->discord_id)
                    ->etc()
            );
    }

    public function test_can_delete_crossroads_user(): void
    {
        $this->authenticate();

        $user = CrossroadsUser::factory()->create();

        $response = $this->deleteJson("/api/crossroads-users/{$user->id}");

        $response->assertNoContent();

        $this->assertDatabaseMissing('crossroads_users', ['id' => $user->id]);
    }

    public function test_can_find_by_minecraft_uuid(): void
    {
        $this->authenticate();

        $user = CrossroadsUser::factory()->create(['minecraft_uuid' => 'test-uuid']);

        $response = $this->getJson("/api/crossroads-users/minecraft/{$user->minecraft_uuid}");

        $response->assertOk()
            ->assertJson(
                fn(AssertableJson $json) =>
                $json->where('id', $user->id)
                    ->where('minecraft_uuid', 'test-uuid')
                    ->etc()
            );
    }

    public function test_can_find_by_discord_id(): void
    {
        $this->authenticate();

        $user = CrossroadsUser::factory()->create(['discord_id' => '999999']);

        $response = $this->getJson("/api/crossroads-users/discord/{$user->discord_id}");

        $response->assertOk()
            ->assertJson(
                fn(AssertableJson $json) =>
                $json->where('id', $user->id)
                    ->where('discord_id', '999999')
                    ->etc()
            );
    }

    public function test_returns_404_when_crossroads_user_not_found(): void
    {
        $this->authenticate();

        $response = $this->getJson('/api/crossroads-users/999999');

        $response->assertStatus(404);
    }

    public function test_validates_required_fields_on_create(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/crossroads-users', []); // Empty payload

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Either minecraft_uuid or discord_id must be provided.'
            ]);
    }
}
