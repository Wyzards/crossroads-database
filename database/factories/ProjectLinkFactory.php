<?php

namespace Database\Factories;

use App\Models\ProjectLink;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectLinkFactory extends Factory
{
    protected $model = ProjectLink::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => $this->faker->words(2, true),
            'url' => $this->faker->url(),
        ];
    }
}
