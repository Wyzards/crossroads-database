<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Project;
use App\Models\ProjectLink;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProjectLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_belongs_to_project()
    {
        $project = Project::factory()->create();
        $link = ProjectLink::factory()->create(['project_id' => $project->id]);

        $this->assertInstanceOf(Project::class, $link->project);
    }

}
