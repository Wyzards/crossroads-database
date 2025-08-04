<?php

namespace Tests\Unit\Models;

use App\Models\Map;
use App\Models\MapInstance;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MapTest extends TestCase
{
    use RefreshDatabase;

    public function test_instances_relationship()
    {
        $map = Map::factory()->create();
        $instances = MapInstance::factory()->count(3)->create(['map_id' => $map->id]);

        $this->assertCount(3, $map->instances);
        $this->assertInstanceOf(MapInstance::class, $map->instances->first());
    }

}
