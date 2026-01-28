<?php
// File: app/Notifications/DuplicateSubscriptionsDetected.php

namespace App\Notifications;

use App\Models\Team;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DuplicateSubscriptionsDetected extends Notification
{
    public function __construct(
        private readonly Team $team
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('⚠️ Duplicate Subscriptions Detected')
            ->line('We noticed you have multiple active subscriptions:')
            ->line('• Pro Plan ($9/mo) - Personal subscription')
            ->line('• Team Plan ($29/mo) - Team: ' . $this->team->name)
            ->line('You are currently being charged for both ($38/mo total).')
            ->line('To avoid double charges, please cancel one of these subscriptions.')
            ->action('Manage Subscriptions', route('billing.index'))
            ->line('If you have questions, please contact support.');
    }
}