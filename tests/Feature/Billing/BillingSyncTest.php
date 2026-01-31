<?php

namespace Tests\Feature\Billing;

use App\Models\Team;
use App\Models\User;
use App\Services\Billing\PlanLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingSyncTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test: User after checkout hits /app/billing/success (HTTP 200, no redirect)
     */
    public function test_success_page_returns_200_no_redirect(): void
    {
        $user = User::factory()->create([
            'billing_plan' => PlanLimits::PLAN_FREE,
            'billing_status' => 'free',
        ]);

        $response = $this->actingAs($user)->get('/app/billing/success');

        $response->assertStatus(200);
        $response->assertDontSee('Redirecting');
    }

    /**
     * Test: sync-status endpoint returns correct JSON shape
     */
    public function test_sync_status_returns_correct_json_shape(): void
    {
        $user = User::factory()->create([
            'billing_plan' => PlanLimits::PLAN_PRO,
            'billing_status' => 'active',
            'next_bill_at' => now()->addMonth(),
        ]);

        $response = $this->actingAs($user)->getJson('/app/billing/sync-status');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'current_plan',
            'billing_status',
            'next_billing_date',
            'checkout_in_progress',
            'last_webhook_processed_at',
            'is_synced',
        ]);

        $response->assertJson([
            'current_plan' => 'pro',
            'billing_status' => 'active',
            'is_synced' => true,
        ]);
    }

    /**
     * Test: sync-status shows checkout_in_progress correctly
     */
    public function test_sync_status_shows_checkout_in_progress(): void
    {
        $user = User::factory()->create([
            'billing_plan' => PlanLimits::PLAN_FREE,
            'billing_status' => 'free',
            'checkout_in_progress_until' => now()->addMinutes(5),
        ]);

        $response = $this->actingAs($user)->getJson('/app/billing/sync-status');

        $response->assertStatus(200);
        $response->assertJson([
            'checkout_in_progress' => true,
        ]);
    }

    /**
     * Test: clear-pending endpoint clears checkout lock
     */
    public function test_clear_pending_clears_checkout_lock(): void
    {
        $user = User::factory()->create([
            'billing_plan' => PlanLimits::PLAN_FREE,
            'billing_status' => 'free',
            'checkout_in_progress_until' => now()->addMinutes(5),
        ]);

        $this->assertTrue($user->checkout_in_progress_until->isFuture());

        $response = $this->actingAs($user)->postJson('/app/billing/clear-pending');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $user->refresh();
        $this->assertNull($user->checkout_in_progress_until);
    }

    /**
     * Test: Team member cannot clear pending checkout (only owner)
     */
    public function test_team_member_cannot_clear_pending_checkout(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $team = Team::factory()->create([
            'user_id' => $owner->id,
            'personal_team' => false,
            'billing_plan' => PlanLimits::PLAN_TEAM,
            'billing_status' => 'active',
            'checkout_in_progress_until' => now()->addMinutes(5),
        ]);

        $team->users()->attach($member->id, ['role' => 'member']);
        $member->current_team_id = $team->id;
        $member->save();

        $response = $this->actingAs($member)->postJson('/app/billing/clear-pending');

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
        ]);
    }

    /**
     * Test: Sync-status endpoint is auth protected
     */
    public function test_sync_status_requires_auth(): void
    {
        $response = $this->getJson('/app/billing/sync-status');

        $response->assertStatus(401);
    }

    /**
     * Test: Clear-pending endpoint is auth protected
     */
    public function test_clear_pending_requires_auth(): void
    {
        $response = $this->postJson('/app/billing/clear-pending');

        $response->assertStatus(401);
    }

    /**
     * Test: Success page accessible even when billing is not yet active
     */
    public function test_success_page_accessible_during_sync(): void
    {
        $user = User::factory()->create([
            'billing_plan' => PlanLimits::PLAN_FREE,
            'billing_status' => 'free',
            'checkout_in_progress_until' => now()->addMinutes(5),
        ]);

        $response = $this->actingAs($user)->get('/app/billing/success');

        $response->assertStatus(200);
        $response->assertSee('Syncing your billing');
    }

    /**
     * Test: Success page shows synced state when billing is active
     */
    public function test_success_page_shows_synced_when_active(): void
    {
        $user = User::factory()->create([
            'billing_plan' => PlanLimits::PLAN_PRO,
            'billing_status' => 'active',
        ]);

        $response = $this->actingAs($user)->get('/app/billing/success');

        $response->assertStatus(200);
        $response->assertSee('Plan updated successfully');
    }

    /**
     * Test: Team owner sees team billing status in sync-status
     */
    public function test_team_owner_sees_team_billing_in_sync_status(): void
    {
        $owner = User::factory()->create();

        $team = Team::factory()->create([
            'user_id' => $owner->id,
            'personal_team' => false,
            'billing_plan' => PlanLimits::PLAN_TEAM,
            'billing_status' => 'active',
            'paddle_subscription_id' => 'sub_test_123',
        ]);

        $owner->current_team_id = $team->id;
        $owner->save();

        $response = $this->actingAs($owner)->getJson('/app/billing/sync-status');

        $response->assertStatus(200);
        $response->assertJson([
            'current_plan' => 'team',
            'billing_status' => 'active',
        ]);
    }
}
