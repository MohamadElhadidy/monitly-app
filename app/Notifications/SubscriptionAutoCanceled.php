<?php
// File: app/Notifications/SubscriptionAutoCanceled.php

namespace App\Notifications;

use App\Models\Team;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionAutoCanceled extends Notification
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
        $nextBillDate = $notifiable->next_bill_at?->format('F d, Y') ?? 'end of billing period';
        
        return (new MailMessage)
            ->subject('✅ Your Pro Subscription Was Canceled')
            ->line('Good news! We automatically canceled your Pro subscription since you upgraded to our Team plan.')
            ->line('**Changes:**')
            ->line('• Pro Plan ($9/mo) - Canceled (ends ' . $nextBillDate . ')')
            ->line('• Team Plan ($29/mo) - Active for team: ' . $this->team->name)
            ->line('You\'ll continue to have Pro access until ' . $nextBillDate . ', then full Team features will activate.')
            ->action('View Billing Details', route('billing.index'))
            ->line('Thank you for upgrading to Team!');
    }
}