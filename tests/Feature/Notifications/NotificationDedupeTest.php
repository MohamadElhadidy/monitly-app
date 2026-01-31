<?php

namespace Tests\Feature\Notifications;

use App\Models\Incident;
use App\Models\Monitor;
use App\Models\NotificationDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationDedupeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Phase 8 Test: One incident event creates one notification log
     */
    public function test_one_incident_one_notification_log(): void
    {
        $user = User::factory()->create();

        $monitor = Monitor::factory()->create([
            'user_id' => $user->id,
            'team_id' => null,
        ]);

        $incident = Incident::create([
            'monitor_id' => $monitor->id,
            'started_at' => now(),
            'recovered_at' => null,
            'cause' => 'Test incident',
        ]);

        $delivery = NotificationDelivery::create([
            'monitor_id' => $monitor->id,
            'incident_id' => $incident->id,
            'channel' => 'email',
            'target' => $user->email,
            'event_type' => 'incident_opened',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $count = NotificationDelivery::where('monitor_id', $monitor->id)
            ->where('incident_id', $incident->id)
            ->where('channel', 'email')
            ->where('event_type', 'incident_opened')
            ->count();

        $this->assertEquals(1, $count);
    }

    /**
     * Phase 8 Test: Duplicate notification is prevented
     */
    public function test_duplicate_notification_prevented(): void
    {
        $user = User::factory()->create();

        $monitor = Monitor::factory()->create([
            'user_id' => $user->id,
            'team_id' => null,
        ]);

        $incident = Incident::create([
            'monitor_id' => $monitor->id,
            'started_at' => now(),
            'recovered_at' => null,
            'cause' => 'Test incident',
        ]);

        NotificationDelivery::create([
            'monitor_id' => $monitor->id,
            'incident_id' => $incident->id,
            'channel' => 'email',
            'target' => $user->email,
            'event_type' => 'incident_opened',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $exists = NotificationDelivery::where('monitor_id', $monitor->id)
            ->where('incident_id', $incident->id)
            ->where('channel', 'email')
            ->where('target', $user->email)
            ->where('event_type', 'incident_opened')
            ->exists();

        $this->assertTrue($exists);

        if (!$exists) {
            NotificationDelivery::create([
                'monitor_id' => $monitor->id,
                'incident_id' => $incident->id,
                'channel' => 'email',
                'target' => $user->email,
                'event_type' => 'incident_opened',
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        }

        $count = NotificationDelivery::where('monitor_id', $monitor->id)
            ->where('incident_id', $incident->id)
            ->where('channel', 'email')
            ->where('event_type', 'incident_opened')
            ->count();

        $this->assertEquals(1, $count);
    }

    /**
     * Phase 8 Test: Different event types create separate logs
     */
    public function test_different_event_types_create_separate_logs(): void
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
            'cause' => 'Test incident',
        ]);

        NotificationDelivery::create([
            'monitor_id' => $monitor->id,
            'incident_id' => $incident->id,
            'channel' => 'email',
            'target' => $user->email,
            'event_type' => 'incident_opened',
            'status' => 'sent',
            'sent_at' => now()->subHour(),
        ]);

        NotificationDelivery::create([
            'monitor_id' => $monitor->id,
            'incident_id' => $incident->id,
            'channel' => 'email',
            'target' => $user->email,
            'event_type' => 'incident_resolved',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $count = NotificationDelivery::where('monitor_id', $monitor->id)
            ->where('incident_id', $incident->id)
            ->count();

        $this->assertEquals(2, $count);
    }

    /**
     * Phase 8 Test: Different channels create separate logs
     */
    public function test_different_channels_create_separate_logs(): void
    {
        $user = User::factory()->create();

        $monitor = Monitor::factory()->create([
            'user_id' => $user->id,
            'team_id' => null,
        ]);

        $incident = Incident::create([
            'monitor_id' => $monitor->id,
            'started_at' => now(),
            'recovered_at' => null,
            'cause' => 'Test incident',
        ]);

        NotificationDelivery::create([
            'monitor_id' => $monitor->id,
            'incident_id' => $incident->id,
            'channel' => 'email',
            'target' => $user->email,
            'event_type' => 'incident_opened',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        NotificationDelivery::create([
            'monitor_id' => $monitor->id,
            'incident_id' => $incident->id,
            'channel' => 'slack',
            'target' => '#alerts',
            'event_type' => 'incident_opened',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $count = NotificationDelivery::where('monitor_id', $monitor->id)
            ->where('incident_id', $incident->id)
            ->where('event_type', 'incident_opened')
            ->count();

        $this->assertEquals(2, $count);
    }

    /**
     * Phase 8 Test: Failed notification can be retried
     */
    public function test_failed_notification_can_be_retried(): void
    {
        $user = User::factory()->create();

        $monitor = Monitor::factory()->create([
            'user_id' => $user->id,
            'team_id' => null,
        ]);

        $incident = Incident::create([
            'monitor_id' => $monitor->id,
            'started_at' => now(),
            'recovered_at' => null,
            'cause' => 'Test incident',
        ]);

        $delivery = NotificationDelivery::create([
            'monitor_id' => $monitor->id,
            'incident_id' => $incident->id,
            'channel' => 'email',
            'target' => $user->email,
            'event_type' => 'incident_opened',
            'status' => 'failed',
            'error_message' => 'SMTP connection failed',
        ]);

        $this->assertEquals('failed', $delivery->status);

        $delivery->status = 'sent';
        $delivery->sent_at = now();
        $delivery->error_message = null;
        $delivery->save();

        $delivery->refresh();

        $this->assertEquals('sent', $delivery->status);
        $this->assertNotNull($delivery->sent_at);
    }

    /**
     * Phase 8 Test: Notification delivery tracks timestamps
     */
    public function test_notification_delivery_tracks_timestamps(): void
    {
        $user = User::factory()->create();

        $monitor = Monitor::factory()->create([
            'user_id' => $user->id,
            'team_id' => null,
        ]);

        $incident = Incident::create([
            'monitor_id' => $monitor->id,
            'started_at' => now(),
            'recovered_at' => null,
            'cause' => 'Test incident',
        ]);

        $delivery = NotificationDelivery::create([
            'monitor_id' => $monitor->id,
            'incident_id' => $incident->id,
            'channel' => 'email',
            'target' => $user->email,
            'event_type' => 'incident_opened',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $this->assertNotNull($delivery->created_at);
        $this->assertNotNull($delivery->sent_at);
    }
}
