<?php

namespace App\Mail;

use App\Models\Monitor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MonitorSlaBreachMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Monitor $monitor,
        public array $stats,
        public float $targetPct,
    ) {
        $this->onQueue('notifications');
    }

    public function build(): self
    {
        $uptime = number_format((float) ($this->stats['uptime_pct'] ?? 0), 4);
        $subject = "SLA breach: {$this->monitor->name} ({$uptime}% < ".number_format($this->targetPct, 1)."%)";

        return $this->from('support@monitly.app', 'Monitly')
            ->replyTo('support@monitly.app', 'Monitly Support')
            ->subject($subject)
            ->view('emails.monitor_sla_breached', [
                'monitor' => $this->monitor,
                'stats' => $this->stats,
                'targetPct' => $this->targetPct,
                'appUrl' => rtrim((string) config('app.url'), '/'),
            ]);
    }
}
