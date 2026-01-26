<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Audit\Audit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_writes(): void
    {
        $u = User::factory()->create();

        $this->actingAs($u);

        Audit::log(action: 'test.action', subject: $u, meta: ['x' => 1]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'test.action',
            'actor_type' => 'user',
            'actor_id' => $u->id,
            'subject_type' => User::class,
            'subject_id' => $u->id,
        ]);

        $row = AuditLog::query()->where('action', 'test.action')->first();
        $this->assertSame(1, (int) ($row->meta['x'] ?? 0));
    }
}