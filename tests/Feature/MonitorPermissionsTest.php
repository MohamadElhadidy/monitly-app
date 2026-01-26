<?php

namespace Tests\Feature;

use App\Models\Monitor;
use App\Models\MonitorMemberPermission;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class MonitorPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private function makeTeamWithUsers(): array
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $member = User::factory()->create();

        $team = Team::factory()->create([
            'user_id' => $owner->id,
            'personal_team' => false,
            'name' => 'QA Team',
        ]);

        // Ensure users belong to team with roles
        $team->users()->attach($owner->id, ['role' => 'admin']); // not required, but keeps membership consistent
        $team->users()->attach($admin->id, ['role' => 'admin']);
        $team->users()->attach($member->id, ['role' => 'member']);

        // Set current team for users
        $owner->switchTeam($team);
        $admin->switchTeam($team);
        $member->switchTeam($team);

        return [$team, $owner, $admin, $member];
    }

    public function test_member_cannot_view_team_monitor_without_grant(): void
    {
        [$team, $owner, $admin, $member] = $this->makeTeamWithUsers();

        $monitor = Monitor::factory()->forTeam($team, $owner)->create();

        $this->actingAs($member)
            ->get(route('monitors.show', $monitor))
            ->assertForbidden();
    }

    public function test_member_can_view_team_monitor_with_any_grant(): void
    {
        [$team, $owner, $admin, $member] = $this->makeTeamWithUsers();

        $monitor = Monitor::factory()->forTeam($team, $owner)->create([
            'name' => 'Member Visible',
        ]);

        MonitorMemberPermission::query()->create([
            'monitor_id' => $monitor->id,
            'user_id' => $member->id,
            'view_logs' => false,
            'receive_alerts' => true, // ANY permission grants visibility
            'pause_resume' => false,
            'edit_settings' => false,
        ]);

        $this->actingAs($member)
            ->get(route('monitors.show', $monitor))
            ->assertOk()
            ->assertSee('Member Visible');
    }

    public function test_view_logs_section_hidden_without_view_logs_permission(): void
    {
        [$team, $owner, $admin, $member] = $this->makeTeamWithUsers();

        $monitor = Monitor::factory()->forTeam($team, $owner)->create([
            'name' => 'Logs Hidden',
        ]);

        MonitorMemberPermission::query()->create([
            'monitor_id' => $monitor->id,
            'user_id' => $member->id,
            'view_logs' => false,
            'receive_alerts' => true,
            'pause_resume' => false,
            'edit_settings' => false,
        ]);

        $this->actingAs($member)
            ->get(route('monitors.show', $monitor))
            ->assertOk()
            ->assertSee('Access restricted')
            ->assertDontSee('Last 10 checks');
    }

    public function test_pause_resume_gate_requires_permission_for_member(): void
    {
        [$team, $owner, $admin, $member] = $this->makeTeamWithUsers();
        $monitor = Monitor::factory()->forTeam($team, $owner)->create();

        // Member with visibility but no pause
        MonitorMemberPermission::query()->create([
            'monitor_id' => $monitor->id,
            'user_id' => $member->id,
            'view_logs' => true,
            'receive_alerts' => false,
            'pause_resume' => false,
            'edit_settings' => false,
        ]);

        $this->assertFalse(Gate::forUser($member)->allows('pauseResume', $monitor));

        // Grant pause
        MonitorMemberPermission::query()
            ->where('monitor_id', $monitor->id)
            ->where('user_id', $member->id)
            ->update(['pause_resume' => true]);

        $this->assertTrue(Gate::forUser($member)->allows('pauseResume', $monitor));
    }

    public function test_owner_only_gates_for_billing_and_members(): void
    {
        [$team, $owner, $admin, $member] = $this->makeTeamWithUsers();

        $this->assertTrue(Gate::forUser($owner)->allows('team.manageBilling', $team));
        $this->assertFalse(Gate::forUser($admin)->allows('team.manageBilling', $team));
        $this->assertFalse(Gate::forUser($member)->allows('team.manageBilling', $team));

        $this->assertTrue(Gate::forUser($owner)->allows('team.manageMembers', $team));
        $this->assertFalse(Gate::forUser($admin)->allows('team.manageMembers', $team));
        $this->assertFalse(Gate::forUser($member)->allows('team.manageMembers', $team));
    }
}
