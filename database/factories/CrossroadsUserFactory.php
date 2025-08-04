<?php

namespace Database\Factories;

use App\Models\CrossroadsUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Faker\Provider\Uuid;

class CrossroadsUserFactory extends Factory
{
    protected $model = CrossroadsUser::class;

    public function definition(): array
    {
        $this->faker->addProvider(new Uuid($this->faker));

        return [
            'minecraft_uuid' => Str::uuid()->toString(),
            'discord_id' => $this->faker->unique()->numerify('##########'),
        ];
    }
}
