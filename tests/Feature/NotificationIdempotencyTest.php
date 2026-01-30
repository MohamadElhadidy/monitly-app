<?php

namespace Tests\Feature;

use App\Jobs\Notifications\BuildNotificationBatchJob;
use App\Jobs\Notifications\SendEmailNotificationJob;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Services\Notifications\MonitorAlertRecipientResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_notification_batch_is_idempotent(): void
    {
        Queue::fake();

        $owner = User::factory()->create(['email' => 'owner@example.test']);
        $monitor = Monitor::factory()->individual($owner)->create([
            'paused' => false,
            'email_alerts_enabled' => true,
        ]);

        $incident = Incident::factory()->create([
            'monitor_id' => $monitor->id,
            'started_at' => now(),
            'recovered_at' => null,
        ]);

        $job = new BuildNotificationBatchJob($monitor->id, $incident->id, 'monitor.down');
        $job->handle(app(MonitorAlertRecipientResolver::class));
        $job->handle(app(MonitorAlertRecipientResolver::class));

        $this->assertSame(1, NotificationDelivery::query()->count());
        Queue::assertPushed(SendEmailNotificationJob::class, 1);
    }
}
