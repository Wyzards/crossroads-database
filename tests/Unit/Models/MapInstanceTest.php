<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Map;
use App\Models\MapInstance;
use App\Models\CrossroadsUser;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MapInstanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_belongs_to_map()
    {
        $map = Map::factory()->create();
        $instance = MapInstance::factory()->create(['map_id' => $map->id]);

        $this->assertInstanceOf(Map::class, $instance->map);
        $this->assertEquals($map->id, $instance->map->id);
    }

    public function test_belongs_to_owner()
    {
        $user = CrossroadsUser::factory()->create();
        $instance = MapInstance::factory()->create(['owner_id' => $user->id]);

        $this->assertInstanceOf(CrossroadsUser::class, $instance->owner);
        $this->assertEquals($user->id, $instance->owner->id);
    }

    public function test_secondary_users_relationship()
    {
        $instance = MapInstance::factory()->create();
        $users = CrossroadsUser::factory()->count(2)->create();
        $instance->secondaryUsers()->attach($users);

        $this->assertCount(2, $instance->secondaryUsers);
        $this->assertInstanceOf(CrossroadsUser::class, $instance->secondaryUsers->first());
    }

}
