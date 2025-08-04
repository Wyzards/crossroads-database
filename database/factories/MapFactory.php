<?php

namespace Database\Factories;

use App\Models\Map;
use Illuminate\Database\Eloquent\Factories\Factory;

class MapFactory extends Factory
{
    protected $model = Map::class;

    public function definition(): array
    {
        $minPlayers = $this->faker->numberBetween(1, 4);
        $maxPlayers = $this->faker->numberBetween($minPlayers, $minPlayers + 8);

        return [
            'name' => $this->faker->words(2, true), // e.g., "Sky Island"
            'image_name' => $this->faker->unique()->slug . '.png',
            'docker_folder' => $this->faker->unique()->slug,
            'allocated_ram_mb' => $this->faker->numberBetween(512, 8192),
            'description' => $this->faker->optional()->paragraph,
            'min_players' => $minPlayers,
            'max_players' => $maxPlayers,
        ];
    }
}
