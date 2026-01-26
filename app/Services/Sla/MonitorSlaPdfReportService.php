<?php

namespace App\Services\Sla;

use App\Models\Incident;
use App\Models\Monitor;
use App\Models\SlaReport;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class MonitorSlaPdfReportService
{
    public function __construct(
        private readonly SlaCalculator $calculator,
        private readonly SlaTargetResolver $targets,
    ) {}

    public function generate(Monitor $monitor, ?User $actor = null, int $windowDays = 30, int $linkMinutes = 30): array
    {
        $now = now();
        $windowStart = $now->copy()->subDays($windowDays);
        $windowEnd = $now->copy();

        $stats = $this->calculator->forMonitor($monitor, $now, $windowDays);
        $targetPct = $this->targets->targetPctForMonitor($monitor);

        $incidents = Incident::query()
            ->where('monitor_id', $monitor->id)
            ->where('sla_counted', true)
            ->where('started_at', '<=', $windowEnd)
            ->where(function ($q) use ($windowStart) {
                $q->whereNull('recovered_at')
                  ->orWhere('recovered_at', '>=', $windowStart);
            })
            ->orderByDesc('started_at')
            ->get(['id', 'started_at', 'recovered_at', 'cause_summary', 'sla_counted', 'downtime_seconds']);

        $incidentRows = [];
        foreach ($incidents as $inc) {
            $iStart = $inc->started_at ? $inc->started_at->copy() : null;
            $iEnd = $inc->recovered_at ? $inc->recovered_at->copy() : $windowEnd->copy();

            $windowOverlapSeconds = 0;
            if ($iStart) {
                $windowOverlapSeconds = $this->overlapSeconds(
                    $iStart,
                    $iEnd,
                    $windowStart,
                    $windowEnd
                );
            }

            $incidentRows[] = [
                'id' => (int) $inc->id,
                'started_at' => $inc->started_at,
                'recovered_at' => $inc->recovered_at,
                'cause_summary' => (string) ($inc->cause_summary ?: 'Incident'),
                'counted' => (bool) $inc->sla_counted,
                'downtime_seconds_in_window' => (int) $windowOverlapSeconds,
                'is_open' => $inc->recovered_at ? false : true,
            ];
        }

        $payload = [
            'app_name' => 'Monitly',
            'generated_at' => $now,
            'monitor' => $monitor,
            'window_start' => $windowStart,
            'window_end' => $windowEnd,
            'target_pct' => $targetPct,
            'stats' => $stats,
            'incidents' => $incidentRows,
        ];

        $pdf = Pdf::loadView('pdf.monitor_sla_report', $payload)
            ->setPaper('a4')
            ->setOptions([
                // Security hardening for PDFs:
                'isRemoteEnabled' => false,
                'isHtml5ParserEnabled' => true,
                'defaultFont' => 'DejaVu Sans',
            ]);

        $bytes = $pdf->output();

        $uuid = (string) Str::uuid();
        $safeName = Str::slug((string) $monitor->name) ?: 'monitor';
        $fileName = "sla-{$safeName}-{$now->format('Ymd-His')}-{$uuid}.pdf";

        $path = "sla-reports/monitor-{$monitor->id}/{$fileName}";

        Storage::disk('local')->put($path, $bytes);

        $report = SlaReport::query()->create([
            'monitor_id' => $monitor->id,
            'team_id' => $monitor->team_id,
            'generated_by_user_id' => $actor?->id,
            'window_start' => $windowStart,
            'window_end' => $windowEnd,
            'storage_path' => $path,
            'sha256' => hash('sha256', $bytes),
            'size_bytes' => strlen($bytes),
            'expires_at' => $now->copy()->addMinutes($linkMinutes),
        ]);

        $url = URL::temporarySignedRoute(
            'sla.reports.download',
            $report->expires_at,
            ['monitor' => $monitor->id, 'report' => $report->id]
        );

        return [
            'report' => $report,
            'download_url' => $url,
        ];
    }

    private function overlapSeconds($start, $end, $winStart, $winEnd): int
    {
        $start = $start->copy();
        $end = $end->copy();
        $winStart = $winStart->copy();
        $winEnd = $winEnd->copy();

        if ($end->lessThanOrEqualTo($winStart)) return 0;
        if ($start->greaterThanOrEqualTo($winEnd)) return 0;

        $effStart = $start->lessThan($winStart) ? $winStart : $start;
        $effEnd = $end->greaterThan($winEnd) ? $winEnd : $end;

        if ($effEnd->lessThanOrEqualTo($effStart)) return 0;

        return (int) $effStart->diffInSeconds($effEnd);
    }
}