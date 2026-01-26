<?php

namespace App\Mail;

use App\Models\Incident;
use App\Models\Monitor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MonitorDownMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Monitor $monitor,
        public Incident $incident,
    ) {
        $this->onQueue('mail');
    }

    public function envelope(): Envelope
    {
        $subject = "DOWN: {$this->monitor->name}";

        return new Envelope(
            from: new Address('notify@monitly.app', 'Monitly'),
            replyTo: [new Address('notify@monitly.app', 'Monitly')],
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.monitor-down',
            with: [
                'monitorName' => $this->monitor->name,
                'monitorUrl' => $this->monitor->url,
                'incidentStartedAt' => optional($this->incident->started_at)->format('Y-m-d H:i:s') ?? '—',
                'appMonitorUrl' => $this->appMonitorUrl(),
                'teamName' => $this->monitor->team?->name,
            ]
        );
    }

    private function appMonitorUrl(): string
    {
        try {
            return route('monitors.show', $this->monitor);
        } catch (\Throwable) {
            return rtrim((string) config('app.url'), '/') . '/app/monitors/' . $this->monitor->id;
        }
    }
}
