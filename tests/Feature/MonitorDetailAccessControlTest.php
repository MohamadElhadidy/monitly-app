<?php

namespace Tests\Feature;

use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\MonitorMemberPermission;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonitorDetailAccessControlTest extends TestCase
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
            'name' => 'UI Test Team',
        ]);

        $team->users()->attach($owner->id, ['role' => 'admin']);
        $team->users()->attach($admin->id, ['role' => 'admin']);
        $team->users()->attach($member->id, ['role' => 'member']);

        $owner->switchTeam($team);
        $admin->switchTeam($team);
        $member->switchTeam($team);

        return [$team, $owner, $admin, $member];
    }

    public function test_member_cannot_see_permissions_section(): void
    {
        [$team, $owner, $admin, $member] = $this->makeTeamWithUsers();

        $monitor = Monitor::factory()->forTeam($team, $owner)->create([
            'name' => 'Perm Section Hidden',
        ]);

        // Give member visibility but not admin
        MonitorMemberPermission::query()->create([
            'monitor_id' => $monitor->id,
            'user_id' => $member->id,
            'view_logs' => true,
            'receive_alerts' => false,
            'pause_resume' => false,
            'edit_settings' => false,
        ]);

        $this->actingAs($member)
            ->get(route('monitors.show', $monitor))
            ->assertOk()
            ->assertSee('Perm Section Hidden')
            ->assertDontSee('Member permissions');
    }

    public function test_admin_can_see_permissions_section(): void
    {
        [$team, $owner, $admin, $member] = $this->makeTeamWithUsers();

        $monitor = Monitor::factory()->forTeam($team, $owner)->create([
            'name' => 'Perm Section Visible',
        ]);

        $this->actingAs($admin)
            ->get(route('monitors.show', $monitor))
            ->assertOk()
            ->assertSee('Member permissions');
    }

    public function test_checks_tab_restricted_without_view_logs_permission(): void
    {
        [$team, $owner, $admin, $member] = $this->makeTeamWithUsers();

        $monitor = Monitor::factory()->forTeam($team, $owner)->create([
            'name' => 'Checks Restricted',
        ]);

        // Some checks exist
        MonitorCheck::factory()->count(3)->forMonitor($monitor)->create();

        // Member can view monitor but has no view_logs
        MonitorMemberPermission::query()->create([
            'monitor_id' => $monitor->id,
            'user_id' => $member->id,
            'view_logs' => false,
            'receive_alerts' => true, // still grants visibility
            'pause_resume' => false,
            'edit_settings' => false,
        ]);

        $this->actingAs($member)
            ->get(route('monitors.show', $monitor) . '?tab=checks')
            ->assertOk()
            ->assertSee('Access restricted')
            ->assertSee('You don’t have permission to view checks for this monitor.')
            ->assertDontSee('Checked at');
    }

    public function test_checks_tab_visible_with_view_logs_permission(): void
    {
        [$team, $owner, $admin, $member] = $this->makeTeamWithUsers();

        $monitor = Monitor::factory()->forTeam($team, $owner)->create([
            'name' => 'Checks Allowed',
        ]);

        MonitorCheck::factory()->count(2)->forMonitor($monitor)->create();

        MonitorMemberPermission::query()->create([
            'monitor_id' => $monitor->id,
            'user_id' => $member->id,
            'view_logs' => true,
            'receive_alerts' => false,
            'pause_resume' => false,
            'edit_settings' => false,
        ]);

        $this->actingAs($member)
            ->get(route('monitors.show', $monitor) . '?tab=checks')
            ->assertOk()
            ->assertSee('Checks Log')
            ->assertSee('Checked at');
    }

    public function test_user_outside_team_cannot_access_monitor(): void
    {
        [$team, $owner, $admin, $member] = $this->makeTeamWithUsers();

        $outsider = User::factory()->create();

        $monitor = Monitor::factory()->forTeam($team, $owner)->create();

        $this->actingAs($outsider)
            ->get(route('monitors.show', $monitor))
            ->assertForbidden();
    }
}
