<?php

namespace Tests\Unit\Models;

use App\Models\CrossroadsUser;
use App\Models\MapInstance;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CrossroadsUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_owned_instances_relationship()
    {
        $user = CrossroadsUser::factory()->create();
        $instances = MapInstance::factory()->count(2)->create(['owner_id' => $user->id]);

        $this->assertCount(2, $user->ownedInstances);
        $this->assertInstanceOf(MapInstance::class, $user->ownedInstances->first());
    }

    public function test_secondary_instances_relationship()
    {
        $user = CrossroadsUser::factory()->create();
        $instances = MapInstance::factory()->count(2)->create();
        $user->secondaryInstances()->attach($instances);

        $this->assertCount(2, $user->secondaryInstances);
        $this->assertInstanceOf(MapInstance::class, $user->secondaryInstances->first());
    }

}
