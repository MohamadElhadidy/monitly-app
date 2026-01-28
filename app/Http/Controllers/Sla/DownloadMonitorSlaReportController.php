<?php

namespace App\Http\Controllers\Sla;

use App\Models\Monitor;
use App\Models\SlaReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DownloadMonitorSlaReportController
{
    public function __invoke(Request $request, Monitor $monitor, SlaReport $report)
    {
        // 'signed' middleware validates signature + expiration (temporarySignedRoute)
        // Enforce report belongs to monitor
        if ((int) $report->monitor_id !== (int) $monitor->id) {
            abort(404);
        }

        // Access control: must be allowed to view the monitor
        abort_unless($request->user()->can('view', $monitor), 403);

        // Additional server-side expiry gate (defense in depth)
        if ($report->expires_at && $report->expires_at->isPast()) {
            abort(410, 'This download link has expired.');
        }

        $path = (string) $report->storage_path;

        if (! Storage::disk('local')->exists($path)) {
            abort(404, 'Report file not found.');
        }

        $safeName = Str::slug((string) $monitor->name) ?: 'monitor';
        $fileName = "sla-report-{$safeName}-{$report->window_end->format('Ymd')}.pdf";

        return Storage::disk('local')->download($path, $fileName, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}