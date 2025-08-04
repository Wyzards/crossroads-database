<?php

namespace Database\Factories;

use App\Models\ProjectStaff;
use App\Models\Project;
use App\Models\CrossroadsUser;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectStaffFactory extends Factory
{
    protected $model = ProjectStaff::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'user_id' => CrossroadsUser::factory(),
            'rank' => $this->faker->numberBetween(0, 1), // 0 = LEAD, 1 = STAFF
        ];
    }
}