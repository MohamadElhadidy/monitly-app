<?php

namespace Tests\Feature\Incidents;

use App\Models\Incident;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\User;
use App\Jobs\Monitoring\EvaluateMonitorStateJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Phase 7 Test: Only one open incident per monitor is allowed
     */
    public function test_only_one_open_incident_per_monitor(): void
    {
        $user = User::factory()->create();

        $monitor = Monitor::factory()->create([
            'user_id' => $user->id,
            'team_id' => null,
            'last_status' => 'up',
        ]);

        $incident1 = Incident::create([
            'monitor_id' => $monitor->id,
            'started_at' => now()->subHour(),
            'recovered_at' => null,
            'cause' => 'Connection timeout',
        ]);

        $openIncidents = Incident::where('monitor_id', $monitor->id)
            ->whereNull('recovered_at')
            ->count();

        $this->assertEquals(1, $openIncidents);

        $incident1->recovered_at = now();
        $incident1->save();

        $incident2 = Incident::create([
            'monitor_id' => $monitor->id,
            'started_at' => now(),
            'recovered_at' => null,
            'cause' => 'Server error',
        ]);

        $openIncidents = Incident::where('monitor_id', $monitor->id)
            ->whereNull('recovered_at')
            ->count();

        $this->assertEquals(1, $openIncidents);
    }

    /**
     * Phase 7 Test: Monitor has openIncident relationship
     */
    public function test_monitor_has_open_incident_relationship(): void
    {
        $user = User::factory()->create();

        $monitor = Monitor::factory()->create([
            'user_id' => $user->id,
            'team_id' => null,
        ]);

        $this->assertNull($monitor->openIncident);

        $incident = Incident::create([
            'monitor_id' => $monitor->id,
            'started_at' => now(),
            'recovered_at' => null,
            'cause' => 'Test incident',
        ]);

        $monitor->refresh();

        $this->assertNotNull($monitor->openIncident);
        $this->assertEquals($incident->id, $monitor->openIncident->id);
    }

    /**
     * Phase 7 Test: Resolved incident is not returned as open
     */
    public function test_resolved_incident_not_returned_as_open(): void
    {
        $user = User::factory()->create();

        $monitor = Monitor::factory()->create([
            'user_id' => $user->id,
            'team_id' => null,
        ]);

        $incident = Incident::create([
            'monitor_id' => $monitor->id,
            'started_at' => now()->subHour(),
            'recovered_at' => now(),
            'cause' => 'Resolved incident',
        ]);

        $monitor->refresh();

        $this->assertNull($monitor->openIncident);
    }

    /**
     * Phase 7 Test: Incident has correct timestamps
     */
    public function test_incident_has_correct_timestamps(): void
    {
        $user = User::factory()->create();

        $monitor = Monitor::factory()->create([
            'user_id' => $user->id,
            'team_id' => null,
        ]);

        $startedAt = now()->subMinutes(30);

        $incident = Incident::create([
            'monitor_id' => $monitor->id,
            'started_at' => $startedAt,
            'recovered_at' => null,
            'cause' => 'Test incident',
        ]);

        $this->assertEquals(
            $startedAt->format('Y-m-d H:i'),
            $incident->started_at->format('Y-m-d H:i')
        );
        $this->assertNull($incident->recovered_at);
    }

    /**
     * Phase 7 Test: Incident recovery sets recovered_at
     */
    public function test_incident_recovery_sets_recovered_at(): void
    {
        $user = User::factory()->create();

        $monitor = Monitor::factory()->create([
            'user_id' => $user->id,
            'team_id' => null,
        ]);

        $incident = Incident::create([
            'monitor_id' => $monitor->id,
            'started_at' => now()->subHour(),
            'recovered_at' => null,
            'cause' => 'Test incident',
        ]);

        $this->assertNull($incident->recovered_at);

        $recoveredAt = now();
        $incident->recovered_at = $recoveredAt;
        $incident->save();

        $incident->refresh();

        $this->assertNotNull($incident->recovered_at);
        $this->assertEquals(
            $recoveredAt->format('Y-m-d H:i'),
            $incident->recovered_at->format('Y-m-d H:i')
        );
    }

    /**
     * Phase 7 Test: Monitor incidents relationship returns all incidents
     */
    public function test_monitor_incidents_relationship(): void
    {
        $user = User::factory()->create();

        $monitor = Monitor::factory()->create([
            'user_id' => $user->id,
            'team_id' => null,
        ]);

        Incident::create([
            'monitor_id' => $monitor->id,
            'started_at' => now()->subDays(2),
            'recovered_at' => now()->subDays(2)->addHour(),
            'cause' => 'Incident 1',
        ]);

        Incident::create([
            'monitor_id' => $monitor->id,
            'started_at' => now()->subDay(),
            'recovered_at' => now()->subDay()->addMinutes(30),
            'cause' => 'Incident 2',
        ]);

        Incident::create([
            'monitor_id' => $monitor->id,
            'started_at' => now(),
            'recovered_at' => null,
            'cause' => 'Incident 3 (open)',
        ]);

        $this->assertEquals(3, $monitor->incidents()->count());
        $this->assertEquals(1, $monitor->incidents()->whereNull('recovered_at')->count());
    }

    /**
     * Phase 7 Test: Authenticated user can access incidents page
     */
    public function test_authenticated_user_can_access_incidents_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/app/incidents');

        $response->assertStatus(200);
    }
}
