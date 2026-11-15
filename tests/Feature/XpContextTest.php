<?php

namespace Tests\Feature;

use App\Models\Badge;
use App\Models\CrossroadsUser;
use App\Models\User;
use App\Models\UserBadgeXp;
use App\Models\XpEventDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class XpContextTest extends TestCase
{
    use RefreshDatabase;

    private function setUpEvent(int $cooldown): array
    {
        $this->actingAs(User::factory()->create(), 'sanctum');
        $user = CrossroadsUser::factory()->create();
        $badge = Badge::create([
            'name' => 'Test badge',
            'description' => 'x',
            'category' => 'community',
            'is_xp_based' => true,
            'rarity' => 'common',
            'is_active' => true,
        ]);
        $def = XpEventDefinition::create([
            'name' => 'Test event',
            'badge_id' => $badge->id,
            'xp_amount' => 5,
            'cooldown_seconds' => $cooldown,
        ]);

        return [$user, $def, $badge];
    }

    private function trigger($user, $def, ?string $context = null)
    {
        return $this->postJson(
            "/api/crossroads-users/{$user->id}/xp-events/{$def->id}/trigger",
            $context === null ? [] : ['context' => $context]
        );
    }

    public function test_context_awards_once_per_context_and_ignores_cooldown(): void
    {
        [$user, $def, $badge] = $this->setUpEvent(604800);

        $this->trigger($user, $def, 'vetting_vote:1')->assertOk();
        $this->trigger($user, $def, 'vetting_vote:1')->assertNoContent();
        $this->trigger($user, $def, 'vetting_vote:2')->assertOk();

        $this->assertSame(10, UserBadgeXp::where('user_id', $user->id)->where('badge_id', $badge->id)->first()->total_xp);
    }

    public function test_no_context_keeps_cooldown(): void
    {
        [$user, $def] = $this->setUpEvent(604800);

        $this->trigger($user, $def)->assertOk();
        $this->trigger($user, $def)->assertNoContent();
    }

    public function test_no_context_without_cooldown_awards_each_time(): void
    {
        [$user, $def] = $this->setUpEvent(0);

        $this->trigger($user, $def)->assertOk();
        $this->trigger($user, $def)->assertOk();
    }

    public function test_invalid_context_is_rejected(): void
    {
        [$user, $def] = $this->setUpEvent(0);

        $this->trigger($user, $def, 'Bad Context')->assertStatus(422);
    }
}
