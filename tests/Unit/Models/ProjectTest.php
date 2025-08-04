<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Project;
use App\Models\ProjectLink;
use App\Models\ProjectAttachment;
use App\Models\ProjectStaff;
use App\ProjectStatus;
use App\ProjectType;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_links_relationship()
    {
        $project = Project::factory()->create();
        ProjectLink::factory()->count(2)->create(['project_id' => $project->id]);

        $this->assertCount(2, $project->links);
        $this->assertInstanceOf(ProjectLink::class, $project->links->first());
    }

    public function test_project_attachments_relationship()
    {
        $project = Project::factory()->create();
        ProjectAttachment::factory()->count(2)->create(['project_id' => $project->id]);

        $this->assertCount(2, $project->attachments);
        $this->assertInstanceOf(ProjectAttachment::class, $project->attachments->first());
    }

    public function test_project_staff_relationship()
    {
        $project = Project::factory()->create();
        ProjectStaff::factory()->count(2)->create(['project_id' => $project->id]);

        $this->assertCount(2, $project->staff);
        $this->assertInstanceOf(ProjectStaff::class, $project->staff->first());
    }

    public function test_status_and_type_are_casted_to_enums()
    {
        $project = Project::factory()->create([
            'status' => ProjectStatus::ARCHIVED,
            'type' => ProjectType::MMO,
        ]);

        $this->assertInstanceOf(ProjectStatus::class, $project->status);
        $this->assertEquals(ProjectStatus::ARCHIVED, $project->status);

        $this->assertInstanceOf(ProjectType::class, $project->type);
        $this->assertEquals(ProjectType::MMO, $project->type);
    }
}
