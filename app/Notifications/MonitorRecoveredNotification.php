<?php

namespace App\Notifications;

use App\Models\Incident;
use App\Models\Monitor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MonitorRecoveredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Monitor $monitor,
        public Incident $incident
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $url = route('monitors.show', $this->monitor);
        
        $downtimeMinutes = round($this->incident->downtime_seconds / 60, 1);
        $downtimeFormatted = $this->formatDowntime($this->incident->downtime_seconds);
        
        return (new MailMessage)
            ->success()
            ->subject('✅ Monitor Recovered: ' . $this->monitor->name)
            ->greeting('Good News!')
            ->line('Your monitor **' . $this->monitor->name . '** has recovered and is back online.')
            ->line('**URL:** ' . $this->monitor->url)
            ->line('**Downtime:** ' . $downtimeFormatted)
            ->line('**Started:** ' . $this->incident->started_at->format('M d, Y H:i:s'))
            ->line('**Recovered:** ' . $this->incident->recovered_at->format('M d, Y H:i:s'))
            ->action('View Incident Details', $url)
            ->line('Your monitor is being actively checked again.')
            ->salutation('— Your Monitly Team');
    }

    private function formatDowntime(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return "{$hours}h {$minutes}m {$secs}s";
        } elseif ($minutes > 0) {
            return "{$minutes}m {$secs}s";
        } else {
            return "{$secs}s";
        }
    }
}