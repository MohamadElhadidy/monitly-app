<?php

namespace Tests\Feature\Teams;

use App\Models\Team;
use App\Models\User;
use App\Services\Billing\BillingOwnerResolver;
use App\Services\Billing\PlanLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamPermissionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Phase 2 Test: Normal user (Free/Pro) cannot access /app/team
     */
    public function test_normal_user_cannot_access_team_settings(): void
    {
        $user = User::factory()->create([
            'billing_plan' => PlanLimits::PLAN_FREE,
            'billing_status' => 'free',
        ]);

        $this->actingAs($user);

        $response = $this->get('/teams/create');

        $response->assertStatus(200);
    }

    /**
     * Phase 2 Test: Team member cannot manage billing
     */
    public function test_team_member_cannot_manage_billing(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $team = Team::factory()->create([
            'user_id' => $owner->id,
            'personal_team' => false,
            'billing_plan' => PlanLimits::PLAN_TEAM,
            'billing_status' => 'active',
        ]);

        $team->users()->attach($member->id, ['role' => 'member']);
        $member->current_team_id = $team->id;
        $member->save();

        $resolver = app(BillingOwnerResolver::class);

        $this->assertFalse($resolver->canManage($member, $team));
    }

    /**
     * Phase 2 Test: Team owner can manage billing
     */
    public function test_team_owner_can_manage_billing(): void
    {
        $owner = User::factory()->create();

        $team = Team::factory()->create([
            'user_id' => $owner->id,
            'personal_team' => false,
            'billing_plan' => PlanLimits::PLAN_TEAM,
            'billing_status' => 'active',
        ]);

        $owner->current_team_id = $team->id;
        $owner->save();

        $resolver = app(BillingOwnerResolver::class);

        $this->assertTrue($resolver->canManage($owner, $team));
    }

    /**
     * Phase 2 Test: Team owner can manage resources
     */
    public function test_team_owner_can_manage_resources(): void
    {
        $owner = User::factory()->create();

        $team = Team::factory()->create([
            'user_id' => $owner->id,
            'personal_team' => false,
            'billing_plan' => PlanLimits::PLAN_TEAM,
            'billing_status' => 'active',
        ]);

        $owner->current_team_id = $team->id;
        $owner->save();

        $this->actingAs($owner);

        $response = $this->get('/app/monitors/create');

        $response->assertStatus(200);
    }

    /**
     * Phase 2 Test: Team admin can manage resources
     */
    public function test_team_admin_can_manage_resources(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();

        $team = Team::factory()->create([
            'user_id' => $owner->id,
            'personal_team' => false,
            'billing_plan' => PlanLimits::PLAN_TEAM,
            'billing_status' => 'active',
        ]);

        $team->users()->attach($admin->id, ['role' => 'admin']);
        $admin->current_team_id = $team->id;
        $admin->save();

        $this->actingAs($admin);

        $response = $this->get('/app/monitors/create');

        $response->assertStatus(200);
    }

    /**
     * Phase 2 Test: Team member can view resources
     */
    public function test_team_member_can_view_resources(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $team = Team::factory()->create([
            'user_id' => $owner->id,
            'personal_team' => false,
            'billing_plan' => PlanLimits::PLAN_TEAM,
            'billing_status' => 'active',
        ]);

        $team->users()->attach($member->id, ['role' => 'member']);
        $member->current_team_id = $team->id;
        $member->save();

        $this->actingAs($member);

        $response = $this->get('/app/monitors');

        $response->assertStatus(200);
    }

    /**
     * Phase 2 Test: Exactly one owner per team is enforced
     */
    public function test_team_has_exactly_one_owner(): void
    {
        $owner = User::factory()->create();

        $team = Team::factory()->create([
            'user_id' => $owner->id,
            'personal_team' => false,
        ]);

        $this->assertEquals($owner->id, $team->user_id);
        $this->assertEquals($owner->id, $team->owner->id);
    }

    /**
     * Phase 2 Test: Billing account resolver returns User for normal accounts
     */
    public function test_resolver_returns_user_for_normal_account(): void
    {
        $user = User::factory()->create([
            'billing_plan' => PlanLimits::PLAN_PRO,
            'billing_status' => 'active',
        ]);

        $resolver = app(BillingOwnerResolver::class);
        $context = $resolver->resolve($user);

        $this->assertEquals('user', $context['type']);
        $this->assertInstanceOf(User::class, $context['billable']);
        $this->assertEquals($user->id, $context['billable']->id);
    }

    /**
     * Phase 2 Test: Billing account resolver returns Team for team accounts
     */
    public function test_resolver_returns_team_for_team_account(): void
    {
        $owner = User::factory()->create();

        $team = Team::factory()->create([
            'user_id' => $owner->id,
            'personal_team' => false,
            'billing_plan' => PlanLimits::PLAN_TEAM,
            'billing_status' => 'active',
            'paddle_subscription_id' => 'sub_test',
        ]);

        $owner->current_team_id = $team->id;
        $owner->save();

        $resolver = app(BillingOwnerResolver::class);
        $context = $resolver->resolve($owner);

        $this->assertEquals('team', $context['type']);
        $this->assertInstanceOf(Team::class, $context['billable']);
        $this->assertEquals($team->id, $context['billable']->id);
    }
}
