<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Project;
use App\Models\ProjectAttachment;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProjectAttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_belongs_to_project()
    {
        $project = Project::factory()->create();
        $attachment = ProjectAttachment::factory()->create(['project_id' => $project->id]);

        $this->assertInstanceOf(Project::class, $attachment->project);
    }
}
