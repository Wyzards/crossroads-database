<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\ProjectAttachment;
use App\Models\Project;

class ProjectAttachmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $projects = Project::all();

        if ($projects->isEmpty())
        {
            $this->command->info('No projects found, skipping ProjectAttachment seeding.');
            return;
        }

        foreach ($projects as $project)
        {
            // Create a few attachments per project
            for ($i = 0; $i < rand(1, 3); $i++)
            {
                $filename = 'file_' . Str::random(6) . '.txt';
                ProjectAttachment::create([
                    'project_id' => $project->id,
                    'path' => '/storage/projects/' . $project->id . '/' . $filename,
                ]);
            }
        }
    }
}
