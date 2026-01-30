<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Admin\AdminActionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminActionAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_created_on_suspend(): void
    {
        config(['admin.owner_email' => 'owner@example.com']);

        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $target = User::factory()->create();

        $this->actingAs($owner);

        app(AdminActionService::class)->suspendUser($target, 'Policy violation');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.user.suspended',
            'subject_id' => $target->id,
        ]);

        $log = AuditLog::query()->where('action', 'admin.user.suspended')->first();
        $this->assertEquals('Policy violation', $log->meta['reason']);
    }
}
