<?php

namespace Tests\Feature;

use App\Models\Badge;
use App\Models\CrossroadsUser;
use App\Models\GameNight;
use App\Models\GameNightAttendee;
use App\Models\Project;
use App\Models\ProjectList;
use App\Models\User;
use App\Models\UserBadgeXp;
use App\Models\UserXpEvent;
use App\Models\XpEventDefinition;
use App\Models\VettingCycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VettingTest extends TestCase
{
    use RefreshDatabase;

    private function project(string $name, string $vetted = 'vetting_queue'): Project
    {
        return Project::forceCreate([
            'name' => $name, 'display_name' => $name, 'architect_approval' => 'approved', 'community_vetted' => $vetted,
            'accessibility' => 'public', 'project_stage' => 'released',
        ]);
    }

    private function lease(string $holder = 'test'): string
    {
        $this->actingAs(User::factory()->create(), 'sanctum');
        return $this->postJson('/api/vetting/lease', ['holder' => $holder, 'ttl_seconds' => 120])->assertOk()->json('token');
    }

    private function cycle(string $token, string $week = '2026-11-20'): int
    {
        return $this->postJson('/api/vetting/cycles', ['week_of' => $week, 'lease_token' => $token])->assertOk()->json('id');
    }

    // ---- lease ----

    public function test_lease_acquire_conflict_renew_release(): void
    {
        $token = $this->lease('a');
        $this->postJson('/api/vetting/lease', ['holder' => 'b', 'ttl_seconds' => 60])->assertStatus(409)->assertJsonPath('holder', 'a');
        $this->putJson('/api/vetting/lease', ['token' => $token, 'ttl_seconds' => 120])->assertOk();
        $this->putJson('/api/vetting/lease', ['token' => 'nope', 'ttl_seconds' => 120])->assertStatus(409);

        $settings = $this->getJson('/api/vetting/settings')->assertOk()->json();
        $this->assertSame('a', $settings['lease']['holder']);
        $this->assertStringNotContainsString($token, json_encode($settings));

        $this->postJson('/api/vetting/lease/release', ['token' => 'nope'])->assertNoContent();
        $this->postJson('/api/vetting/lease', ['holder' => 'b', 'ttl_seconds' => 60])->assertStatus(409); // wrong token did not release
        $this->postJson('/api/vetting/lease/release', ['token' => $token])->assertNoContent();
        $this->postJson('/api/vetting/lease', ['holder' => 'b', 'ttl_seconds' => 60])->assertOk();
    }

    public function test_expired_lease_can_be_taken_and_old_token_is_fenced(): void
    {
        $old = $this->lease('a');
        $this->travel(3)->minutes();
        $this->putJson('/api/vetting/lease', ['token' => $old, 'ttl_seconds' => 120])->assertStatus(409); // lost
        $this->postJson('/api/vetting/cycles', ['week_of' => '2026-11-20', 'lease_token' => $old])->assertStatus(409);

        $new = $this->postJson('/api/vetting/lease', ['holder' => 'b', 'ttl_seconds' => 120])->assertOk()->json('token');
        $this->postJson('/api/vetting/cycles', ['week_of' => '2026-11-20', 'lease_token' => $old])->assertStatus(409);
        $this->postJson('/api/vetting/cycles', ['week_of' => '2026-11-20', 'lease_token' => $new])->assertOk();
    }

    public function test_writes_need_the_current_lease_but_settings_do_not(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum');
        $this->postJson('/api/vetting/cycles', ['week_of' => '2026-11-20'])->assertStatus(409);
        $this->postJson('/api/vetting/cancel', ['project_id' => 1, 'to' => 'unvetted'])->assertStatus(409);

        $this->putJson('/api/vetting/settings', ['enabled' => true])->assertOk()->assertJsonPath('enabled', true);
        $this->assertNotNull($this->getJson('/api/vetting/settings')->json('enabled_at'));
        $this->putJson('/api/vetting/settings', ['enabled' => false])->assertOk()->assertJsonPath('enabled', false);
        $this->putJson('/api/vetting/settings', ['ping_vetting_role_id' => '123'])->assertOk()->assertJsonPath('ping_vetting_role_id', '123');
    }

    public function test_enabled_at_set_only_on_false_to_true(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum');
        $this->putJson('/api/vetting/settings', ['enabled' => true])->assertOk();
        $first = $this->getJson('/api/vetting/settings')->json('enabled_at');
        $this->travel(1)->hours();
        $this->putJson('/api/vetting/settings', ['enabled' => true])->assertOk();
        $this->assertSame($first, $this->getJson('/api/vetting/settings')->json('enabled_at'));
    }

    public function test_reenabling_after_a_disable_keeps_the_first_enabled_at(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum');
        $this->putJson('/api/vetting/settings', ['enabled' => true])->assertOk();
        $first = $this->getJson('/api/vetting/settings')->json('enabled_at');
        $this->travel(2)->hours();
        $this->putJson('/api/vetting/settings', ['enabled' => false])->assertOk();
        $this->travel(2)->hours();
        $this->putJson('/api/vetting/settings', ['enabled' => true])->assertOk()->assertJsonPath('enabled', true);
        $this->assertSame($first, $this->getJson('/api/vetting/settings')->json('enabled_at'));
    }

    public function test_candidate_policy_defaults_to_queue_only_three_and_validates(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum');
        $this->getJson('/api/vetting/settings')->assertOk()->assertJsonPath('hunt_in_poll', false)->assertJsonPath('max_candidates', 3);
        $this->putJson('/api/vetting/settings', ['hunt_in_poll' => true, 'max_candidates' => 10])->assertOk()
            ->assertJsonPath('hunt_in_poll', true)->assertJsonPath('max_candidates', 10);
        $this->putJson('/api/vetting/settings', ['max_candidates' => 11])->assertStatus(422);
        $this->putJson('/api/vetting/settings', ['max_candidates' => 0])->assertStatus(422);
    }

    // ---- cycles ----

    public function test_create_cycle_is_idempotent(): void
    {
        $token = $this->lease();
        $a = $this->cycle($token);
        $this->assertSame($a, $this->cycle($token));
        $this->assertSame(1, VettingCycle::count());
        $this->getJson('/api/vetting/cycles?week_of=2026-11-20')->assertOk()->assertJsonPath('0.id', $a)->assertJsonPath('0.week_of', '2026-11-20');
    }

    public function test_write_once_fields(): void
    {
        $token = $this->lease();
        $id = $this->cycle($token);
        $p1 = $this->project('one');
        $p2 = $this->project('two');

        $this->putJson("/api/vetting/cycles/$id", ['chosen_project_id' => $p1->id, 'chosen_by' => 'oldest', 'lease_token' => $token])->assertOk();
        $this->putJson("/api/vetting/cycles/$id", ['chosen_project_id' => $p1->id, 'lease_token' => $token])->assertOk(); // same: no-op
        $this->putJson("/api/vetting/cycles/$id", ['chosen_project_id' => $p2->id, 'lease_token' => $token])->assertStatus(409);
        $this->putJson("/api/vetting/cycles/$id", ['chosen_by' => 'poll', 'lease_token' => $token])->assertStatus(409);
        $this->putJson("/api/vetting/cycles/$id", ['announce_message_id' => '55', 'lease_token' => $token])->assertOk();
        $this->putJson("/api/vetting/cycles/$id", ['announce_message_id' => '56', 'lease_token' => $token])->assertStatus(409);
        $this->assertSame($p1->id, VettingCycle::find($id)->chosen_project_id);
    }

    public function test_step_status_transitions(): void
    {
        $token = $this->lease();
        $id = $this->cycle($token);
        $step = fn(array $b) => $this->postJson("/api/vetting/cycles/$id/steps/close", $b + ['lease_token' => $token]);

        $step(['status' => 'running'])->assertOk()->assertJsonPath('steps.0.attempts', 1);
        $step(['status' => 'failed', 'note' => 'boom', 'next_attempt_at' => '2026-11-20T20:05:00Z'])->assertOk()
            ->assertJsonPath('steps.0.status', 'failed')->assertJsonPath('steps.0.note', 'boom');
        $step(['status' => 'running'])->assertOk()->assertJsonPath('steps.0.attempts', 2)->assertJsonPath('steps.0.next_attempt_at', null);
        $step(['status' => 'done'])->assertOk()->assertJsonPath('steps.0.status', 'done');
        $step(['status' => 'done'])->assertOk();             // same terminal: no-op
        $step(['status' => 'skipped'])->assertStatus(409);   // the other terminal
        $step(['status' => 'running'])->assertStatus(409);
        $this->postJson("/api/vetting/cycles/$id/steps/bogus", ['status' => 'done', 'lease_token' => $token])->assertStatus(422);
    }

    // ---- start ----

    public function test_start_is_idempotent_and_guards(): void
    {
        $token = $this->lease();
        $id = $this->cycle($token);
        $other = $this->cycle($token, '2026-11-27');
        $a = $this->project('a');
        $b = $this->project('b');
        $c = $this->project('c', 'unvetted');
        $start = fn($cid, $p, $from = 'vetting_queue') => $this->postJson("/api/vetting/cycles/$cid/start", ['project_id' => $p->id, 'from' => $from, 'lease_token' => $token]);

        $start($id, $c)->assertStatus(409);                       // not in from
        $start($id, $a)->assertOk()->assertJsonPath('started_project_id', $a->id)->assertJsonPath('started_from', 'vetting_queue');
        $this->assertSame('currently_vetting', $a->fresh()->community_vetted->value);
        $this->assertNotNull($a->fresh()->community_vetted_at);
        $start($id, $a)->assertOk();                              // repeat
        $start($id, $b)->assertStatus(409);                       // cycle already started another
        $start($other, $b)->assertStatus(409);                    // someone is currently vetting
        $this->assertSame('vetting_queue', $b->fresh()->community_vetted->value);
        $this->getJson('/api/vetting/current')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $a->id);
    }


    // ---- step 4: poll fields and the Hunt start ----

    public function test_poll_fields_are_write_once_structures(): void
    {
        $token = $this->lease();
        $id = $this->cycle($token);
        $put = fn(array $b) => $this->putJson("/api/vetting/cycles/$id", $b + ['lease_token' => $token]);
        $cands = [
            ['project_id' => 1, 'source' => 'queue', 'waiting_since' => '2026-11-01T00:00:00Z', 'answer_id' => 1],
            ['project_id' => 2, 'source' => 'hunt', 'waiting_since' => null, 'answer_id' => 2],
        ];

        $put(['candidates' => $cands, 'poll_thread_id' => '900'])->assertOk()->assertJsonPath('candidates.1.source', 'hunt');
        $put(['candidates' => $cands, 'poll_message_id' => '901'])->assertOk();                 // same value: no-op, new field set
        $reordered = array_map(fn($c) => array_reverse($c, true), $cands);
        $put(['candidates' => $reordered])->assertOk();                                          // key order ignored
        $put(['candidates' => array_reverse($cands)])->assertStatus(409);                        // a different list
        $put(['poll_thread_id' => '999'])->assertStatus(409);
        $put(['poll_results' => [['project_id' => 1, 'votes' => 2, 'voter_ids' => ['a', 'b']], ['project_id' => 2, 'votes' => 0, 'voter_ids' => []]]])->assertOk();
        $put(['poll_results' => [['project_id' => 1, 'votes' => 3, 'voter_ids' => ['a', 'b', 'c']]]])->assertStatus(409);
        $put(['candidates' => array_fill(0, 11, $cands[0])])->assertStatus(422);                // Discord's poll limit is 10
        $put(['candidates' => [['project_id' => 1, 'source' => 'elsewhere']]])->assertStatus(422);

        $cycle = VettingCycle::find($id);
        $this->assertSame('901', $cycle->poll_message_id);
        $this->assertCount(2, $cycle->poll_results);
    }

    public function test_start_from_the_hunt_queue_approves_in_the_same_transaction(): void
    {
        $token = $this->lease();
        $id = $this->cycle($token);
        $start = fn($cid, $p, $from) => $this->postJson("/api/vetting/cycles/$cid/start", ['project_id' => $p->id, 'from' => $from, 'lease_token' => $token]);

        $hunt = $this->project('hunt', 'project_hunt_queue');
        $hunt->forceFill(['architect_approval' => 'hidden'])->save();
        $start($id, $hunt, 'vetting_queue')->assertStatus(409);                  // right project, wrong source
        $this->assertSame('hidden', $hunt->fresh()->architect_approval->value);

        $closed = $this->project('closed', 'project_hunt_queue');
        $closed->forceFill(['accessibility' => 'closed'])->save();
        $start($id, $closed, 'project_hunt_queue')->assertStatus(409);           // only public projects are vetted
        $dq = $this->project('dq', 'project_hunt_queue');
        $dq->forceFill(['architect_approval' => 'disqualified'])->save();
        $start($id, $dq, 'project_hunt_queue')->assertStatus(409);
        $this->assertSame('project_hunt_queue', $closed->fresh()->community_vetted->value);

        $start($id, $hunt, 'project_hunt_queue')->assertOk()->assertJsonPath('started_from', 'project_hunt_queue');
        $fresh = $hunt->fresh();
        $this->assertSame('currently_vetting', $fresh->community_vetted->value);
        $this->assertSame('approved', $fresh->architect_approval->value);
        $start($id, $hunt, 'project_hunt_queue')->assertOk();                    // repeat: no-op
        $other = $this->project('other', 'project_hunt_queue');
        $start($this->cycle($token, '2026-11-27'), $other, 'project_hunt_queue')->assertStatus(409); // one vote at a time
        $this->assertSame('project_hunt_queue', $other->fresh()->community_vetted->value);
    }

    // ---- close ----

    public function test_close_is_idempotent_and_guards(): void
    {
        $token = $this->lease();
        $id = $this->cycle($token);
        $other = $this->cycle($token, '2026-11-27');
        $a = $this->project('a', 'currently_vetting');
        $a->forceFill(['vetting_thread_id' => '999'])->save();
        $b = $this->project('b', 'currently_vetting');
        $q = $this->project('q');
        $body = fn($p, $result = 'accepted') => [
            'project_id' => $p->id, 'thread_id' => '999', 'result' => $result, 'up' => 3, 'down' => 1, 'both' => 1,
            'votes' => ['up' => ['1'], 'down' => ['2'], 'both' => []], 'lease_token' => $token,
        ];

        $this->postJson("/api/vetting/cycles/$id/close", $body($q))->assertStatus(409); // not currently_vetting
        $this->postJson("/api/vetting/cycles/$id/close", $body($a))->assertOk()
            ->assertJsonPath('close_result', 'accepted')->assertJsonPath('close_up', 3)->assertJsonPath('close_votes.up.0', '1');
        $a = $a->fresh();
        $this->assertSame('accepted', $a->community_vetted->value);
        $this->assertNull($a->vetting_thread_id);
        $this->postJson("/api/vetting/cycles/$id/close", $body($a, 'rejected'))->assertOk()->assertJsonPath('close_result', 'accepted'); // repeat keeps first
        $this->assertSame('accepted', $a->fresh()->community_vetted->value);
        $this->postJson("/api/vetting/cycles/$id/close", $body($b))->assertStatus(409);  // cycle closed another project
        $this->assertSame('currently_vetting', $b->fresh()->community_vetted->value);
        $this->postJson("/api/vetting/cycles/$other/close", $body($b, 'rejected'))->assertOk();
        $this->assertSame('rejected', $b->fresh()->community_vetted->value);
    }

    // ---- projects ----

    public function test_thread_compare_and_set_and_cancel(): void
    {
        $token = $this->lease();
        $p = $this->project('p');
        $set = fn($thread, $expected) => $this->putJson("/api/vetting/projects/{$p->id}/thread", ['thread_id' => $thread, 'expected' => $expected, 'lease_token' => $token]);

        $set('111', null)->assertOk()->assertJsonPath('vetting_thread_id', '111');
        $set('222', null)->assertStatus(409)->assertJsonPath('vetting_thread_id', '111');
        $set('111', null)->assertOk(); // already the desired value: a retry is a no-op
        $set('222', '111')->assertOk();
        $set(null, '222')->assertOk()->assertJsonPath('vetting_thread_id', null);

        $this->postJson('/api/vetting/cancel', ['project_id' => $p->id, 'to' => 'unvetted', 'lease_token' => $token])->assertStatus(409);
        $p->forceFill(['community_vetted' => 'currently_vetting'])->save();
        $this->postJson('/api/vetting/cancel', ['project_id' => $p->id, 'to' => 'vetting_queue', 'lease_token' => $token])->assertOk();
        $this->assertSame('vetting_queue', $p->fresh()->community_vetted->value);
    }

    public function test_project_update_refuses_currently_vetting_changes(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum');
        $q = $this->project('q');
        $cv = $this->project('cv', 'currently_vetting');

        $this->putJson("/api/projects/{$q->id}", ['community_vetted' => 'currently_vetting'])->assertStatus(409);
        $this->putJson("/api/projects/{$cv->id}", ['community_vetted' => 'accepted'])->assertStatus(409);
        $this->putJson("/api/projects/{$cv->id}", ['community_vetted' => 'currently_vetting', 'display_name' => 'x'])->assertOk(); // unchanged is fine
        $this->putJson("/api/projects/{$q->id}", ['community_vetted' => 'unvetted'])->assertOk();
        $this->assertSame('unvetted', $q->fresh()->community_vetted->value);
    }

    public function test_community_vetted_at_hook_and_columns_not_mass_assignable(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum');
        $p = $this->project('p', 'unvetted');
        $p->forceFill(['community_vetted_at' => null])->saveQuietly();
        $this->assertNull($p->fresh()->community_vetted_at);

        $this->putJson("/api/projects/{$p->id}", ['display_name' => 'renamed'])->assertOk();
        $this->assertNull($p->fresh()->community_vetted_at); // community_vetted not dirty: untouched

        $this->putJson("/api/projects/{$p->id}", ['community_vetted' => 'vetting_queue', 'community_vetted_at' => '2000-01-01', 'vetting_thread_id' => '1'])->assertOk();
        $fresh = $p->fresh();
        $this->assertNotNull($fresh->community_vetted_at);
        $this->assertGreaterThan(2000, $fresh->community_vetted_at->year);
        $this->assertNull($fresh->vetting_thread_id); // neither column is accepted from the API
    }

    // ---- match + channels ----

    public function test_match_lists_is_read_only(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum');
        $rule = fn($f, $o, $v) => ['field' => $f, 'operator' => $o, 'value' => $v];
        ProjectList::create(['name' => 'discovery', 'is_active' => true, 'filters' => ['all' => [
            $rule('architect_approval', '=', 'approved'), $rule('community_vetted', '!=', 'vetting_queue')], 'any' => []]]);
        $p = $this->project('p', 'vetting_queue');

        $this->postJson('/api/project-lists/match', ['project_id' => $p->id])->assertOk()->assertExactJson([]);
        $this->postJson('/api/project-lists/match', ['project_id' => $p->id, 'community_vetted' => 'accepted'])->assertOk()->assertExactJson(['discovery']);
        $this->assertSame('vetting_queue', $p->fresh()->community_vetted->value);
        $this->postJson('/api/project-lists/match', ['architect_approval' => 'approved', 'community_vetted' => 'accepted'])->assertOk()->assertExactJson(['discovery']);
    }

    public function test_system_channels_accept_new_ids(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum');
        $this->postJson('/api/system/channels', ['vetting_forum_id' => '1', 'announcements_channel_id' => '2'])->assertOk();
        $this->getJson('/api/system/channels')->assertOk()->assertJsonPath('vetting_forum_id', '1')->assertJsonPath('announcements_channel_id', '2');
    }

    public function test_settings_row_is_seeded_disabled(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum');
        $this->getJson('/api/vetting/settings')->assertOk()->assertJsonPath('enabled', false)->assertJsonPath('lease', null);
    }

    // ---- game night (step 6) ----

    public function test_event_and_game_night_ids_are_write_once(): void
    {
        $token = $this->lease();
        $id = $this->cycle($token);
        $a = GameNight::create(['type' => 'hunt', 'status' => 'active']);
        $b = GameNight::create(['type' => 'hunt', 'status' => 'ended']);

        $this->putJson("/api/vetting/cycles/$id", ['event_id' => '111', 'game_night_id' => $a->id, 'lease_token' => $token])->assertOk()
            ->assertJsonPath('event_id', '111')->assertJsonPath('game_night_id', $a->id);
        $this->putJson("/api/vetting/cycles/$id", ['event_id' => '111', 'game_night_id' => $a->id, 'lease_token' => $token])->assertOk();
        $this->putJson("/api/vetting/cycles/$id", ['event_id' => '112', 'lease_token' => $token])->assertStatus(409);
        $this->putJson("/api/vetting/cycles/$id", ['game_night_id' => $b->id, 'lease_token' => $token])->assertStatus(409);
        $this->putJson("/api/vetting/cycles/$id", ['game_night_id' => 99999, 'lease_token' => $token])->assertStatus(422);
        $this->postJson('/api/system/channels', ['gamenight_channel_id' => '42'])->assertOk();
        $this->getJson('/api/system/channels')->assertJsonPath('gamenight_channel_id', '42');
    }

    public function test_test_cleanup_removes_only_matching_context_xp_and_empty_game_nights(): void
    {
        $token = $this->lease();
        $user = CrossroadsUser::factory()->create(['discord_id' => '555']);
        $other = CrossroadsUser::factory()->create(['discord_id' => '556']);
        $badge = Badge::create(['name' => 'B', 'description' => 'x', 'category' => 'community', 'is_xp_based' => true, 'rarity' => 'common', 'is_active' => true]);
        $def = XpEventDefinition::create(['name' => 'E', 'badge_id' => $badge->id, 'xp_amount' => 5, 'cooldown_seconds' => 0]);
        $trigger = fn($u, ?string $ctx) => $this->postJson("/api/crossroads-users/{$u->id}/xp-events/{$def->id}/trigger", $ctx === null ? [] : ['context' => $ctx])->assertOk();
        $trigger($user, 'suggestion:1');
        $trigger($user, 'suggestion:2');
        $trigger($user, 'other_kind:1');
        $trigger($user, null);              // a manual award: never touched
        $trigger($other, 'suggestion:1');   // another user: never touched

        $r = $this->postJson('/api/vetting/test-cleanup', ['lease_token' => $token, 'discord_id' => '555', 'contexts' => ['suggestion:1']])->assertOk();
        $this->assertSame(1, $r->json('xp_events_deleted'));
        $this->assertSame(['suggestion:2', 'other_kind:1', null], UserXpEvent::where('user_id', $user->id)->orderBy('id')->pluck('context')->all());
        $this->assertSame(15, UserBadgeXp::where('user_id', $user->id)->first()->total_xp);

        $r = $this->postJson('/api/vetting/test-cleanup', ['lease_token' => $token, 'discord_id' => '555', 'contexts' => ['suggestion:*']])->assertOk();
        $this->assertSame(1, $r->json('xp_events_deleted'));
        $this->assertSame(['other_kind:1', null], UserXpEvent::where('user_id', $user->id)->orderBy('id')->pluck('context')->all());
        $this->assertSame(1, UserXpEvent::where('user_id', $other->id)->count());

        $this->postJson('/api/vetting/test-cleanup', ['lease_token' => $token, 'discord_id' => '555', 'contexts' => ['other_kind:*', 'x:1']])->assertOk();
        $this->assertSame([null], UserXpEvent::where('user_id', $user->id)->pluck('context')->all());
        $this->assertSame(5, UserBadgeXp::where('user_id', $user->id)->first()->total_xp);

        $empty = GameNight::create(['type' => 'hunt', 'status' => 'ended']);
        $busy = GameNight::create(['type' => 'hunt', 'status' => 'ended']);
        GameNightAttendee::create(['game_night_id' => $busy->id, 'user_id' => $user->id, 'joined_at' => now()]);
        $r = $this->postJson('/api/vetting/test-cleanup', ['lease_token' => $token, 'game_night_ids' => [$empty->id, $busy->id]])->assertOk();
        $this->assertSame(1, $r->json('game_nights_deleted'));
        $this->assertSame(1, $r->json('game_nights_kept'));
        $this->assertNull(GameNight::find($empty->id));
        $this->assertNotNull(GameNight::find($busy->id));

        $this->postJson('/api/vetting/test-cleanup', ['discord_id' => '555', 'contexts' => ['suggestion:*']])->assertStatus(409); // fenced
        $this->postJson('/api/vetting/test-cleanup', ['lease_token' => $token, 'discord_id' => '555', 'contexts' => ['*']])->assertStatus(422);
    }

    // ---- hunt suggestions (step 5) ----

    private function suggestion(array $over = []): array
    {
        return $over + ['thread_id' => '9001', 'suggester_discord_id' => '555', 'tier' => 'standard', 'display_name' => 'Cool Server!',
            'ip' => 'play.cool.net', 'discord_invite' => 'https://discord.gg/x', 'text' => 'It has castles.', 'reviewer_discord_id' => '777'];
    }

    public function test_hunt_suggestion_creates_a_hidden_hunt_project(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum');
        $res = $this->postJson('/api/hunt-suggestions', $this->suggestion())->assertCreated();
        $p = Project::findOrFail($res->json('id'));
        $this->assertSame('cool-server', $p->name);
        $this->assertSame('Cool Server!', $p->display_name);
        $this->assertSame('hidden', $p->architect_approval->value);
        $this->assertSame('project_hunt_queue', $p->community_vetted->value);
        $this->assertSame('public', $p->accessibility->value);
        $this->assertSame('released', $p->project_stage->value);
        $this->assertSame('server', $p->format->value);
        $this->assertSame('play.cool.net', $p->ip);
        $this->assertSame('9001', $p->suggestion_thread_id);
        $this->assertSame('555', \App\Models\CrossroadsUser::find($p->suggested_by_user_id)->discord_id);
        $this->assertSame(['It has castles.'], $p->sections()->pluck('text')->all());
    }

    public function test_hunt_suggestion_conflicts(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum');
        $this->postJson('/api/hunt-suggestions', $this->suggestion())->assertCreated();
        // same post again
        $this->postJson('/api/hunt-suggestions', $this->suggestion(['display_name' => 'Other']))->assertStatus(409);
        // same slug, different post
        $this->postJson('/api/hunt-suggestions', $this->suggestion(['thread_id' => '9002', 'display_name' => 'cool server']))->assertStatus(409);
        $this->assertSame(1, Project::count());
        // no text -> no section; an existing suggester row is reused
        $this->postJson('/api/hunt-suggestions', $this->suggestion(['thread_id' => '9003', 'display_name' => 'Another', 'text' => null]))->assertCreated();
        $this->assertSame(1, \App\Models\CrossroadsUser::where('discord_id', '555')->count());
        $this->postJson('/api/hunt-suggestions', $this->suggestion(['thread_id' => '9004', 'display_name' => 'Long', 'text' => str_repeat('x', 2001)]))->assertStatus(422);
        $this->postJson('/api/hunt-suggestions', $this->suggestion(['thread_id' => '9005', 'tier' => 'huge']))->assertStatus(422);
    }
}
