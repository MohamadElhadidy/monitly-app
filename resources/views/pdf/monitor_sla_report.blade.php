@php
    $uptime = number_format((float)($stats['uptime_pct'] ?? 0), 4);
    $target = number_format((float)$target_pct, 1);

    $downtimeSeconds = (int)($stats['downtime_seconds'] ?? 0);
    $incidentsCount = (int)($stats['incident_count'] ?? 0);
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

    $isBreached = ((float)($stats['uptime_pct'] ?? 0)) < (float)$target_pct;
@endphp

<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>SLA Report - {{ $monitor->name }}</title>
    <style>
        @page { margin: 28px; }
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; color: #0f172a; font-size: 12px; }
        .muted { color: #475569; }
        .small { font-size: 11px; }
        .h1 { font-size: 18px; font-weight: 700; margin: 0; }
        .h2 { font-size: 13px; font-weight: 700; margin: 0; }
        .row { width: 100%; }
        .card { border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px; }
        .grid { width: 100%; border-collapse: separate; border-spacing: 10px 10px; }
        .badge-ok { display: inline-block; padding: 4px 8px; border-radius: 999px; background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; font-weight: 700; }
        .badge-breach { display: inline-block; padding: 4px 8px; border-radius: 999px; background: #fff1f2; color: #be123c; border: 1px solid #fecdd3; font-weight: 700; }
        .table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .table th { text-align: left; background: #f8fafc; border: 1px solid #e2e8f0; padding: 8px; font-size: 11px; }
        .table td { border: 1px solid #e2e8f0; padding: 8px; vertical-align: top; }
        .footer { position: fixed; bottom: 10px; left: 28px; right: 28px; color: #94a3b8; font-size: 10px; }
        .hr { height: 1px; background: #e2e8f0; margin: 14px 0; }
        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
    </style>
</head>
<body>
    <table class="row" style="border-collapse: collapse;">
        <tr>
            <td style="width: 60%;">
                <div class="small muted">{{ $app_name }}</div>
                <div class="h1">SLA Report</div>
                <div class="small muted">Rolling 30-day window based on counted incident downtime durations.</div>
            </td>
            <td style="width: 40%; text-align: right;">
                @if ($isBreached)
                    <span class="badge-breach">BREACHED</span>
                @else
                    <span class="badge-ok">OK</span>
                @endif
                <div class="small muted" style="margin-top: 6px;">
                    Generated: {{ $generated_at->format('Y-m-d H:i') }}
                </div>
            </td>
        </tr>
    </table>

    <div class="hr"></div>

    <table class="row" style="border-collapse: collapse;">
        <tr>
            <td style="width: 60%;">
                <div class="h2">{{ $monitor->name }}</div>
                <div class="muted" style="margin-top: 2px;">URL: <span class="mono">{{ $monitor->url }}</span></div>
                <div class="muted small" style="margin-top: 6px;">
                    Window: {{ $window_start->format('Y-m-d H:i') }} → {{ $window_end->format('Y-m-d H:i') }}
                </div>
            </td>
            <td style="width: 40%; text-align: right;">
                <div class="muted small">SLA Target</div>
                <div style="font-size: 18px; font-weight: 800;">{{ $target }}%</div>
            </td>
        </tr>
    </table>

    <table class="grid">
        <tr>
            <td class="card" style="width: 25%;">
                <div class="muted small">Uptime</div>
                <div style="font-size: 18px; font-weight: 800;">{{ $uptime }}%</div>
            </td>
            <td class="card" style="width: 25%;">
                <div class="muted small">Total downtime</div>
                <div style="font-size: 18px; font-weight: 800;">{{ $human($downtimeSeconds) }}</div>
            </td>
            <td class="card" style="width: 25%;">
                <div class="muted small">Incident count</div>
                <div style="font-size: 18px; font-weight: 800;">{{ $incidentsCount }}</div>
            </td>
            <td class="card" style="width: 25%;">
                <div class="muted small">MTTR</div>
                <div style="font-size: 18px; font-weight: 800;">{{ $mttrSeconds ? $human((int)$mttrSeconds) : '—' }}</div>
            </td>
        </tr>
    </table>

    <div class="h2" style="margin-top: 6px;">Incidents (counted)</div>
    <div class="muted small" style="margin-top: 2px;">
        Downtime shown below is the overlap within the report window.
    </div>

    <table class="table">
        <thead>
            <tr>
                <th style="width: 18%;">Started</th>
                <th style="width: 18%;">Recovered</th>
                <th style="width: 14%;">Downtime (in window)</th>
                <th style="width: 40%;">Cause</th>
                <th style="width: 10%;">State</th>
            </tr>
        </thead>
        <tbody>
            @if (count($incidents) === 0)
                <tr>
                    <td colspan="5" class="muted">No counted incidents in the last 30 days.</td>
                </tr>
            @else
                @foreach ($incidents as $i)
                    <tr>
                        <td>{{ $i['started_at'] ? $i['started_at']->format('Y-m-d H:i') : '—' }}</td>
                        <td>{{ $i['recovered_at'] ? $i['recovered_at']->format('Y-m-d H:i') : 'Ongoing' }}</td>
                        <td>{{ $human((int)$i['downtime_seconds_in_window']) }}</td>
                        <td>{{ $i['cause_summary'] }}</td>
                        <td>{{ $i['is_open'] ? 'OPEN' : 'CLOSED' }}</td>
                    </tr>
                @endforeach
            @endif
        </tbody>
    </table>

    <div class="footer">
        {{ $app_name }} SLA Report • Monitor ID: {{ $monitor->id }} • Window: {{ $window_start->format('Y-m-d') }} → {{ $window_end->format('Y-m-d') }}
    </div>
</body>
</html>