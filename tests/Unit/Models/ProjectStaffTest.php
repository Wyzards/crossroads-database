<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Project;
use App\Models\ProjectStaff;
use App\Models\CrossroadsUser;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProjectStaffTest extends TestCase
{
    use RefreshDatabase;

    public function test_belongs_to_project()
    {
        $project = Project::factory()->create();
        $staff = ProjectStaff::factory()->create(['project_id' => $project->id]);

        $this->assertInstanceOf(Project::class, $staff->project);
    }

    public function test_belongs_to_user()
    {
        $user = CrossroadsUser::factory()->create();
        $staff = ProjectStaff::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(CrossroadsUser::class, $staff->user);
    }

}
