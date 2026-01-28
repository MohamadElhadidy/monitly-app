<?php

namespace App\Notifications;

use App\Models\Incident;
use App\Models\Monitor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MonitorDownNotification extends Notification implements ShouldQueue
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
        
        return (new MailMessage)
            ->error()
            ->subject('🚨 Monitor Down: ' . $this->monitor->name)
            ->greeting('Monitor Alert')
            ->line('Your monitor **' . $this->monitor->name . '** is currently down.')
            ->line('**URL:** ' . $this->monitor->url)
            ->line('**Started:** ' . $this->incident->started_at->format('M d, Y H:i:s'))
            ->line('**Status:** ' . ($this->monitor->last_status_code ?? 'No response'))
            ->action('View Monitor Details', $url)
            ->line('We will notify you when the monitor recovers.')
            ->salutation('— Your Monitly Team');
    }
}