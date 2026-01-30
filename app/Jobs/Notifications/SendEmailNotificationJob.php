<?php

namespace App\Jobs\Notifications;

use App\Mail\MonitorDownMail;
use App\Mail\MonitorRecoveredMail;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\NotificationDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendEmailNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 30;

    public function __construct(
        public int $deliveryId,
        public int $monitorId,
        public int $incidentId,
        public string $event,
        public string $email
    ) {
        $this->onQueue('notifications');
    }

    public function backoff(): array
    {
        return [30, 60, 120, 240, 480];
    }

    public function handle(): void
    {
        $delivery = NotificationDelivery::query()->find($this->deliveryId);
        if (! $delivery || $delivery->sent_at) {
            return;
        }

        $monitor = Monitor::query()->find($this->monitorId);
        $incident = Incident::query()->find($this->incidentId);
        if (! $monitor || ! $incident) {
            return;
        }

        try {
            if ($this->event === 'monitor.down') {
                Mail::to($this->email)->send(new MonitorDownMail($monitor, $incident));
            } elseif ($this->event === 'monitor.recovered') {
                Mail::to($this->email)->send(new MonitorRecoveredMail($monitor, $incident));
            } else {
                return;
            }
        } catch (Throwable $e) {
            throw $e;
        }

        $delivery->sent_at = now();
        $delivery->save();
    }
}
