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

class MonitorRecoveredMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Monitor $monitor,
        public Incident $incident,
    ) {
        $this->onQueue('notifications');
    }

    public function envelope(): Envelope
    {
        $subject = "RECOVERED: {$this->monitor->name}";

        return new Envelope(
            from: new Address('notify@monitly.app', 'Monitly'),
            replyTo: [new Address('notify@monitly.app', 'Monitly')],
            subject: $subject,
        );
    }

    public function content(): Content
    {
        $started = optional($this->incident->started_at)->format('Y-m-d H:i:s') ?? '—';
        $recovered = optional($this->incident->recovered_at)->format('Y-m-d H:i:s') ?? '—';
        $seconds = (int) ($this->incident->downtime_seconds ?? 0);

        return new Content(
            markdown: 'emails.monitor-recovered',
            with: [
                'monitorName' => $this->monitor->name,
                'monitorUrl' => $this->monitor->url,
                'incidentStartedAt' => $started,
                'incidentRecoveredAt' => $recovered,
                'downtimeSeconds' => $seconds,
                'downtimeHuman' => $this->humanDuration($seconds),
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

    private function humanDuration(int $seconds): string
    {
        if ($seconds <= 0) return '0s';

        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        $parts = [];
        if ($h > 0) $parts[] = "{$h}h";
        if ($m > 0) $parts[] = "{$m}m";
        if ($s > 0) $parts[] = "{$s}s";

        return implode(' ', $parts);
    }
}
