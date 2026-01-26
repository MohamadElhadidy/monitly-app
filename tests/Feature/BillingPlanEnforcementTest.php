<?php

namespace Tests\Feature;

use App\Actions\Jetstream\InviteTeamMember;
use App\Models\Monitor;
use App\Models\Team;
use App\Models\User;
use App\Services\Billing\PlanEnforcer;
use App\Services\Billing\PlanLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BillingPlanEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_user_cannot_create_more_than_one_monitor(): void
    {
        $user = User::factory()->create([
            'billing_plan' => PlanLimits::PLAN_FREE,
            'billing_status' => 'free',
            'addon_extra_monitor_packs' => 0,
        ]);

        Monitor::query()->create([
            'team_id' => null,
            'user_id' => $user->id,
            'url' => 'https://example.com',
            'name' => 'A',
            'is_public' => false,
            'public_show_url' => false,
            'paused' => false,
            'locked_by_plan' => false,
            'last_status' => 'up',
            'consecutive_failures' => 0,
            'next_check_at' => now(),
        ]);

        $this->expectException(ValidationException::class);

        Monitor::query()->create([
            'team_id' => null,
            'user_id' => $user->id,
            'url' => 'https://example.org',
            'name' => 'B',
            'is_public' => false,
            'public_show_url' => false,
            'paused' => false,
            'locked_by_plan' => false,
            'last_status' => 'up',
            'consecutive_failures' => 0,
            'next_check_at' => now(),
        ]);
    }

    public function test_team_invites_blocked_when_not_team_plan(): void
    {
        $owner = User::factory()->create();

        $team = Team::query()->create([
            'user_id' => $owner->id,
            'name' => 'Owner Team',
            'personal_team' => true,
            'billing_plan' => PlanLimits::PLAN_FREE,
            'billing_status' => 'free',
            'addon_extra_seat_packs' => 0,
            'addon_extra_monitor_packs' => 0,
        ]);

        $owner->current_team_id = $team->id;
        $owner->save();

        $action = app(InviteTeamMember::class);

        $this->expectException(ValidationException::class);

        $action->invite($owner, $team, 'member@example.com', 'member');
    }

    public function test_team_seat_limit_enforced_default_5(): void
    {
        $owner = User::factory()->create();

        $team = Team::query()->create([
            'user_id' => $owner->id,
            'name' => 'Team Plan',
            'personal_team' => true,
            'billing_plan' => PlanLimits::PLAN_TEAM,
            'billing_status' => 'active',
            'addon_extra_seat_packs' => 0, // seat limit = 5
            'addon_extra_monitor_packs' => 0,
        ]);

        // Add 4 members (owner + 4 = 5 seats used)
        $members = User::factory()->count(4)->create();
        foreach ($members as $m) {
            $team->users()->attach($m->id, ['role' => 'member']);
        }

        $owner->current_team_id = $team->id;
        $owner->save();

        $action = app(InviteTeamMember::class);

        $this->expectException(ValidationException::class);

        // This would push beyond 5 (owner +4 + pending invite)
        $action->invite($owner, $team, 'extra@example.com', 'member');
    }

    public function test_grace_downgrade_pauses_and_locks_extra_monitors(): void
    {
        $user = User::factory()->create([
            'billing_plan' => PlanLimits::PLAN_PRO,
            'billing_status' => 'grace',
            'grace_ends_at' => now()->subMinute(),
            'addon_extra_monitor_packs' => 1, // Pro would allow 10, but grace downgrade -> Free allows 1
        ]);

        // Create 3 monitors (will be allowed now because user is Pro)
        for ($i=1; $i<=3; $i++) {
            Monitor::query()->create([
                'team_id' => null,
                'user_id' => $user->id,
                'url' => "https://example{$i}.com",
                'name' => "M{$i}",
                'is_public' => false,
                'public_show_url' => false,
                'paused' => false,
                'locked_by_plan' => false,
                'last_status' => 'up',
                'consecutive_failures' => 0,
                'next_check_at' => now(),
            ]);
        }

        app(PlanEnforcer::class)->enforceGraceDowngrades();

        $user->refresh();

        $this->assertSame(PlanLimits::PLAN_FREE, $user->billing_plan);
        $this->assertSame('free', $user->billing_status);

        $lockedCount = Monitor::query()
            ->where('user_id', $user->id)
            ->whereNull('team_id')
            ->where('locked_by_plan', true)
            ->count();

        $this->assertSame(2, $lockedCount);
    }
}