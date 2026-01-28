@php
    $uptime = number_format((float)($stats['uptime_pct'] ?? 0), 4);
    $target = number_format((float)$targetPct, 1);

    $downtimeSeconds = (int)($stats['downtime_seconds'] ?? 0);
    $incidents = (int)($stats['incident_count'] ?? 0);
    $mttrSeconds = $stats['mttr_seconds'] ?? null;

    $human = function(int $seconds): string {
        $seconds = max(0, $seconds);
        if ($seconds === 0) return '0s';
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;
        $parts = [];
        if ($h > 0) $parts[] = "{$h}h";
        if ($m > 0) $parts[] = "{$m}m";
        if ($s > 0) $parts[] = "{$s}s";
        return implode(' ', $parts);
    };

    $monitorUrl = $appUrl . '/app/monitors/' . $monitor->id;
@endphp

<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>SLA breach</title>
</head>
<body style="margin:0; padding:0; background:#f8fafc; font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;">
    <div style="max-width: 640px; margin: 0 auto; padding: 24px;">
        <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius: 16px; padding:24px;">
            <div style="font-size: 14px; color:#0f172a; font-weight: 700;">Monitly</div>
            <h1 style="margin: 12px 0 0; font-size: 18px; color:#0f172a; font-weight: 700;">
                ⚠️ SLA breach detected
            </h1>
            <p style="margin: 8px 0 0; font-size: 14px; color:#475569;">
                <strong style="color:#0f172a;">{{ $monitor->name }}</strong> is below your SLA target for the rolling 30-day window.
            </p>

            <div style="margin-top: 16px; padding: 16px; background:#fff7ed; border:1px solid #fed7aa; border-radius: 12px;">
                <div style="font-size: 12px; color:#9a3412; font-weight: 700;">SLA status</div>
                <div style="margin-top: 6px; font-size: 14px; color:#9a3412;">
                    Uptime (30d): <strong>{{ $uptime }}%</strong> &nbsp;•&nbsp; Target: <strong>{{ $target }}%</strong>
                </div>
                <div style="margin-top: 6px; font-size: 14px; color:#9a3412;">
                    Downtime: <strong>{{ $human($downtimeSeconds) }}</strong>
                    &nbsp;•&nbsp; Incidents: <strong>{{ $incidents }}</strong>
                    &nbsp;•&nbsp; MTTR: <strong>{{ $mttrSeconds ? $human((int)$mttrSeconds) : '—' }}</strong>
                </div>
            </div>

            <div style="margin-top: 16px;">
                <div style="font-size: 12px; color:#64748b; font-weight: 700;">Monitor</div>
                <div style="margin-top: 6px; font-size: 14px; color:#0f172a; word-break: break-all;">
                    {{ $monitor->url }}
                </div>
            </div>

            <div style="margin-top: 18px;">
                <a href="{{ $monitorUrl }}"
                   style="display:inline-block; background:#0f172a; color:#ffffff; text-decoration:none; padding:10px 14px; border-radius: 10px; font-size: 14px; font-weight: 700;">
                    View monitor details
                </a>
            </div>

            <p style="margin: 18px 0 0; font-size: 12px; color:#64748b;">
                This alert triggers only when uptime crosses below the target (deduplicated). Rolling window uses real incident durations.
            </p>
        </div>

        <div style="margin-top: 12px; font-size: 12px; color:#94a3b8; text-align:center;">
            Sent by Monitly • support@monitly.app
        </div>
    </div>
</body>
</html>
