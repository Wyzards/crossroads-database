<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VettingListRulesTest extends TestCase
{
    use RefreshDatabase;

    private function rule($f, $o, $v) { return ['field' => $f, 'operator' => $o, 'value' => $v]; }

    private function project(string $name, string $approval, string $vetted, string $access = 'public'): Project
    {
        return Project::forceCreate([
            'name' => $name, 'display_name' => $name, 'architect_approval' => $approval, 'community_vetted' => $vetted,
            'accessibility' => $access, 'project_stage' => 'released',
        ]);
    }

    private function lists(): void
    {
        ProjectList::create(['name' => 'discovery', 'is_active' => true, 'filters' => ['all' => [
            $this->rule('architect_approval', '=', 'approved'), $this->rule('project_stage', '!=', 'closed'),
            $this->rule('community_vetted', '!=', 'rejected')], 'any' => []]]);
        ProjectList::create(['name' => 'project-hunt-queue', 'is_active' => true, 'filters' => ['all' => [
            $this->rule('architect_approval', '=', 'hidden'), $this->rule('accessibility', '=', 'public'),
            $this->rule('community_vetted', '=', 'project_hunt_queue')], 'any' => []]]);
        ProjectList::create(['name' => 'archives', 'is_active' => true, 'filters' => [
            'all' => [$this->rule('architect_approval', '!=', 'hidden')],
            'any' => [$this->rule('architect_approval', '=', 'unapproved'), $this->rule('community_vetted', '=', 'rejected'),
                $this->rule('project_stage', '=', 'closed')]]]);
        ProjectList::create(['name' => 'hidden', 'is_active' => true, 'filters' => [
            'all' => [$this->rule('community_vetted', '!=', 'project_hunt_queue')],
            'any' => [$this->rule('architect_approval', '=', 'hidden')]]]);
    }

    private function inList(string $list, Project $p): bool
    {
        return \App\FilterEvaluator::matches($p->fresh('tags'), ProjectList::where('name', $list)->first()->filters);
    }

    public function test_rules_move_queued_and_hunt_projects(): void
    {
        $this->lists();
        $mcr = $this->project('mcr', 'approved', 'vetting_queue');
        $live = $this->project('live', 'approved', 'accepted');
        $hunt = $this->project('destiny', 'unapproved', 'project_hunt_queue');
        $hiddenHunt = $this->project('hh', 'hidden', 'project_hunt_queue');

        $this->artisan('vetting:list-rules', ['--dry' => true])->assertExitCode(0);
        $this->assertTrue($this->inList('discovery', $mcr)); // dry changed nothing

        $this->artisan('vetting:list-rules')->assertExitCode(0);
        $this->assertFalse($this->inList('discovery', $mcr));
        $this->assertTrue($this->inList('discovery', $live));
        $this->assertTrue($this->inList('project-hunt-queue', $hunt));
        $this->assertTrue($this->inList('project-hunt-queue', $hiddenHunt));
        $this->assertFalse($this->inList('project-hunt-queue', $live));
        $this->assertFalse($this->inList('archives', $hunt));
        $this->assertFalse($this->inList('hidden', $hiddenHunt));

        $once = ProjectList::pluck('filters', 'name')->toArray();
        $this->artisan('vetting:list-rules')->assertExitCode(0);
        $this->assertSame($once, ProjectList::pluck('filters', 'name')->toArray()); // idempotent
    }
}
