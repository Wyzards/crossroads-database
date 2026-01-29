<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ProjectLink;
use App\Models\Project;

class ProjectLinkSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $projects = Project::all();

        if ($projects->isEmpty())
        {
            $this->command->info('No projects found, skipping ProjectLink seeding.');
            return;
        }

        foreach ($projects as $project)
        {
            ProjectLink::create([
                'project_id' => $project->id,
                'title' => 'GitHub',
                'url' => 'https://github.com/example/project-' . $project->id
            ]);
        }
    }
}
