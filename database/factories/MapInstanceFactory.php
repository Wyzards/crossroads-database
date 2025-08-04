<?php

namespace Database\Factories;

use App\Models\MapInstance;
use App\Models\Map;
use App\Models\CrossroadsUser;
use Illuminate\Database\Eloquent\Factories\Factory;

class MapInstanceFactory extends Factory
{
    protected $model = MapInstance::class;

    public function definition(): array
    {
        return [
            'map_id' => Map::factory(),
            'owner_id' => CrossroadsUser::factory(),
            'docker_container_id' => $this->faker->uuid,
        ];
    }
}
