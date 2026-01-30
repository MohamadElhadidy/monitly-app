<?php

namespace Tests\Feature;

use App\Jobs\Incidents\OpenIncidentJob;
use App\Models\Incident;
use App\Models\Monitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\DatabaseManager;
use Tests\TestCase;

class IncidentJobIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_incident_job_is_idempotent(): void
    {
        $monitor = Monitor::factory()->create([
            'paused' => false,
            'last_status' => 'down',
        ]);

        $job = new OpenIncidentJob($monitor->id, 500, 'HTTP_STATUS', 'Server error');
        $db = app(DatabaseManager::class);

        $job->handle($db);
        $job->handle($db);

        $this->assertSame(1, Incident::query()->where('monitor_id', $monitor->id)->whereNull('recovered_at')->count());
    }
}
