<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\User;
use App\Models\ProjectAttachment;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TestAttachmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $project = Project::factory()->create(['id' => 1]);

        $attachment = ProjectAttachment::factory()->create([
            'id' => 1,
            'project_id' => $project->id,
            'path' => '/image.jpg',
        ]);

        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);
        $token = $user->createToken('integration')->plainTextToken;
        $this->command->info("Integration Test Token: $token");
    }
}
