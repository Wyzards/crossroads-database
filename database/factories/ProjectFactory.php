<?php

namespace Database\Factories;

use App\Models\Project;
use App\ProjectStatus;
use App\ProjectType;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'channel_id' => $this->faker->optional()->regexify('[A-Za-z0-9]{18}'),
            'guild_id' => $this->faker->regexify('[A-Za-z0-9]{18}'),
            'emoji' => $this->faker->optional()->emoji(),
            'name' => $this->faker->words(3, true),
            'display_name' => $this->faker->words(2, true),
            'status' => $this->faker->randomElement(ProjectStatus::cases())->value, // adjust range as per ProjectStatus enum
            'description' => $this->faker->optional()->paragraph(),
            'ip' => $this->faker->optional()->ipv4(),
            'role_id' => $this->faker->regexify('[A-Za-z0-9]{18}'),
            'type' => $this->faker->randomElement(ProjectType::cases())->value, // adjust range as per ProjectStatus enum
        ];
    }
}
