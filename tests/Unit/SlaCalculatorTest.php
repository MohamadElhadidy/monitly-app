<?php

namespace Tests\Unit;

use App\Models\Incident;
use App\Models\Monitor;
use App\Models\User;
use App\Services\Sla\SlaCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SlaCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculates_uptime_downtime_incidents_and_mttr(): void
    {
        $user = User::factory()->create();
        $monitor = Monitor::query()->create([
            'team_id' => null,
            'user_id' => $user->id,
            'name' => 'M1',
            'url' => 'https://example.com',
            'paused' => false,
            'email_alerts_enabled' => true,
            'slack_alerts_enabled' => true,
            'webhook_alerts_enabled' => true,
            'last_status' => 'up',
            'consecutive_failures' => 0,
            'next_check_at' => now(),
        ]);

        $now = Carbon::parse('2026-01-20 12:00:00');
        $start = $now->copy()->subDays(30);

        // 1) A 60s incident fully inside window
        Incident::query()->create([
            'monitor_id' => $monitor->id,
            'started_at' => $start->copy()->addDays(10)->addMinutes(0),
            'recovered_at' => $start->copy()->addDays(10)->addMinutes(1),
            'downtime_seconds' => 60,
            'cause_summary' => 'HTTP 500',
            'created_by' => 'system',
            'sla_counted' => true,
        ]);

        // 2) A 120s incident fully inside window
        Incident::query()->create([
            'monitor_id' => $monitor->id,
            'started_at' => $start->copy()->addDays(12)->addMinutes(0),
            'recovered_at' => $start->copy()->addDays(12)->addMinutes(2),
            'downtime_seconds' => 120,
            'cause_summary' => 'Timeout',
            'created_by' => 'system',
            'sla_counted' => true,
        ]);

        $calc = app(SlaCalculator::class);
        $stats = $calc->forMonitor($monitor, $now, 30);

        $this->assertSame(180, (int) $stats['downtime_seconds']);
        $this->assertSame(2, (int) $stats['incident_count']);
        $this->assertSame(90, (int) $stats['mttr_seconds']); // average of 60 and 120

        $windowSeconds = (int) $stats['window_seconds'];
        $expectedUptime = round((1 - (180 / $windowSeconds)) * 100, 4);
        $this->assertSame($expectedUptime, (float) $stats['uptime_pct']);
    }

    public function test_counts_overlap_and_truncates_to_window_and_excludes_open_from_mttr(): void
    {
        $user = User::factory()->create();
        $monitor = Monitor::query()->create([
            'team_id' => null,
            'user_id' => $user->id,
            'name' => 'M2',
            'url' => 'https://example.com',
            'paused' => false,
            'email_alerts_enabled' => true,
            'slack_alerts_enabled' => true,
            'webhook_alerts_enabled' => true,
            'last_status' => 'up',
            'consecutive_failures' => 0,
            'next_check_at' => now(),
        ]);

        $now = Carbon::parse('2026-01-20 12:00:00');
        $start = $now->copy()->subDays(30);

        // Spans into the window: starts before window, ends after window start by 10 minutes => count 600s
        Incident::query()->create([
            'monitor_id' => $monitor->id,
            'started_at' => $start->copy()->subHours(1),
            'recovered_at' => $start->copy()->addMinutes(10),
            'downtime_seconds' => null,
            'cause_summary' => 'Spanning',
            'created_by' => 'system',
            'sla_counted' => true,
        ]);

        // Open incident (no recovered_at), overlaps window by 5 minutes until now => counts, but MTTR excludes it
        Incident::query()->create([
            'monitor_id' => $monitor->id,
            'started_at' => $now->copy()->subMinutes(5),
            'recovered_at' => null,
            'downtime_seconds' => null,
            'cause_summary' => 'Open',
            'created_by' => 'system',
            'sla_counted' => true,
        ]);

        $calc = app(SlaCalculator::class);
        $stats = $calc->forMonitor($monitor, $now, 30);

        $this->assertSame(600 + 300, (int) $stats['downtime_seconds']); // 10m + 5m
        $this->assertSame(2, (int) $stats['incident_count']);
        $this->assertSame(600, (int) $stats['mttr_seconds']); // only recovered incident contributes
    }
}
