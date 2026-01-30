<?php

namespace App\Jobs\Sla;

use App\Models\Monitor;
use App\Models\SlaReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class GenerateSlaPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(
        public int $monitorId,
        public ?int $teamId,
        public ?int $requestedByUserId,
        public string $windowStart,
        public string $windowEnd
    ) {
        $this->onQueue('sla');
    }

    public function handle(): void
    {
        $monitor = Monitor::query()->find($this->monitorId);
        if (! $monitor) {
            return;
        }

        $content = "Monitly SLA Report\nMonitor: {$monitor->name}\nWindow: {$this->windowStart} to {$this->windowEnd}\n";
        $path = 'sla-reports/' . $monitor->id . '/' . now()->format('Ymd_His') . '.pdf';

        Storage::disk('local')->put($path, $content);

        $bytes = Storage::disk('local')->size($path);
        $sha = hash('sha256', (string) Storage::disk('local')->get($path));

        SlaReport::query()->create([
            'monitor_id' => $monitor->id,
            'team_id' => $this->teamId,
            'generated_by_user_id' => $this->requestedByUserId,
            'window_start' => $this->windowStart,
            'window_end' => $this->windowEnd,
            'storage_path' => $path,
            'sha256' => $sha,
            'size_bytes' => $bytes,
            'expires_at' => now()->addDays(7),
        ]);
    }
}
