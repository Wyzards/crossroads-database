<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Project;
use App\Models\CrossroadsUser;
use App\Models\ProjectStaff;
use App\ProjectStaffRank;

class ProjectStaffSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $projects = Project::all();
        $users = CrossroadsUser::all();

        if ($projects->isEmpty() || $users->isEmpty())
        {
            $this->command->info('No projects or users found, skipping ProjectStaff seeding.');
            return;
        }

        // Assign random staff roles for demonstration
        foreach ($projects as $project)
        {
            $randomUsers = $users->random(min(3, $users->count()));
            $ranks = [ProjectStaffRank::LEAD, ProjectStaffRank::STAFF];

            foreach ($randomUsers as $user)
            {
                ProjectStaff::create([
                    'project_id' => $project->id,
                    'user_id' => $user->id,
                    'rank' => $ranks[array_rand($ranks)] // or other role enum values
                ]);
            }
        }
    }
}
