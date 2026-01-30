<?php

namespace App\Jobs\Notifications;

use App\Models\Incident;
use App\Models\Monitor;
use App\Models\NotificationChannel;
use App\Models\NotificationDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Notifications\MonitorAlertRecipientResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BuildNotificationBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;

    public function __construct(
        public int $monitorId,
        public int $incidentId,
        public string $event
    ) {
        $this->onQueue('notifications');
    }

    public function handle(MonitorAlertRecipientResolver $recipientResolver): void
    {
        $monitor = Monitor::query()
            ->with(['team', 'owner'])
            ->find($this->monitorId);

        if (! $monitor) {
            return;
        }

        $incident = Incident::query()->find($this->incidentId);
        if (! $incident) {
            return;
        }

        if ((bool) $monitor->email_alerts_enabled) {
            $emails = $recipientResolver->resolveEmails($monitor);
            foreach ($emails as $email) {
                $delivery = NotificationDelivery::query()->firstOrCreate([
                    'incident_id' => $incident->id,
                    'event' => $this->event,
                    'channel' => 'email',
                    'target' => $email,
                ], [
                    'monitor_id' => $monitor->id,
                ]);

                if ($delivery->wasRecentlyCreated) {
                    SendEmailNotificationJob::dispatch($delivery->id, (int) $monitor->id, (int) $incident->id, $this->event, $email)
                        ->onQueue('notifications');
                }
            }
        }

        if ($monitor->team_id && $monitor->team && in_array(strtolower((string) $monitor->team->billing_plan), ['team', 'business'], true)) {
            $channel = NotificationChannel::query()->firstOrCreate(
                ['team_id' => $monitor->team->id],
                ['email_enabled' => true, 'slack_enabled' => false, 'webhooks_enabled' => false]
            );

            if ($channel->slack_enabled && (bool) $monitor->slack_alerts_enabled) {
                $target = 'team:' . $monitor->team->id;
                $delivery = NotificationDelivery::query()->firstOrCreate([
                    'incident_id' => $incident->id,
                    'event' => $this->event,
                    'channel' => 'slack',
                    'target' => $target,
                ], [
                    'monitor_id' => $monitor->id,
                ]);

                if ($delivery->wasRecentlyCreated) {
                    SendIntegrationNotificationJob::dispatch($delivery->id, (int) $monitor->id, (int) $incident->id, $this->event)
                        ->onQueue('notifications');
                }
            }

            if ($channel->webhooks_enabled && (bool) $monitor->webhook_alerts_enabled) {
                $endpoints = WebhookEndpoint::query()
                    ->where('team_id', $monitor->team->id)
                    ->where('enabled', true)
                    ->get();

                foreach ($endpoints as $endpoint) {
                    $delivery = NotificationDelivery::query()->firstOrCreate([
                        'incident_id' => $incident->id,
                        'event' => $this->event,
                        'channel' => 'webhook',
                        'target' => (string) $endpoint->id,
                    ], [
                        'monitor_id' => $monitor->id,
                    ]);

                    if ($delivery->wasRecentlyCreated) {
                        SendWebhookNotificationJob::dispatch($delivery->id, (int) $endpoint->id, (int) $monitor->id, (int) $incident->id, $this->event)
                            ->onQueue('notifications');
                    }
                }
            }
        }
    }
}
