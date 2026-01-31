<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        config()->set('admin.owner_email', 'admin@monitly.app');
    }

    /**
     * Phase 12 Test: Non-owner cannot access /admin
     */
    public function test_non_owner_cannot_access_admin(): void
    {
        $user = User::factory()->create([
            'email' => 'regular@example.com',
        ]);

        $response = $this->actingAs($user)->get('/admin');

        $response->assertStatus(403);
    }

    /**
     * Phase 12 Test: Owner can access /admin
     */
    public function test_owner_can_access_admin(): void
    {
        $owner = User::factory()->create([
            'email' => 'admin@monitly.app',
        ]);

        $response = $this->actingAs($owner)->get('/admin');

        $response->assertStatus(200);
    }

    /**
     * Phase 12 Test: Non-owner cannot access admin users page
     */
    public function test_non_owner_cannot_access_admin_users(): void
    {
        $user = User::factory()->create([
            'email' => 'regular@example.com',
        ]);

        $response = $this->actingAs($user)->get('/admin/users');

        $response->assertStatus(403);
    }

    /**
     * Phase 12 Test: Non-owner cannot access admin teams page
     */
    public function test_non_owner_cannot_access_admin_teams(): void
    {
        $user = User::factory()->create([
            'email' => 'regular@example.com',
        ]);

        $response = $this->actingAs($user)->get('/admin/teams');

        $response->assertStatus(403);
    }

    /**
     * Phase 12 Test: Non-owner cannot access admin webhooks page
     */
    public function test_non_owner_cannot_access_admin_webhooks(): void
    {
        $user = User::factory()->create([
            'email' => 'regular@example.com',
        ]);

        $response = $this->actingAs($user)->get('/admin/webhooks/paddle');

        $response->assertStatus(403);
    }

    /**
     * Phase 12 Test: Non-owner cannot access admin audit page
     */
    public function test_non_owner_cannot_access_admin_audit(): void
    {
        $user = User::factory()->create([
            'email' => 'regular@example.com',
        ]);

        $response = $this->actingAs($user)->get('/admin/audit');

        $response->assertStatus(403);
    }

    /**
     * Phase 12 Test: Guest cannot access admin
     */
    public function test_guest_cannot_access_admin(): void
    {
        $response = $this->get('/admin');

        $response->assertRedirect('/login');
    }

    /**
     * Phase 12 Test: Admin action writes audit log
     */
    public function test_admin_action_writes_audit_log(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@monitly.app',
        ]);

        AuditLog::create([
            'admin_id' => $admin->id,
            'action' => 'user.suspend',
            'target_type' => 'user',
            'target_id' => 123,
            'reason' => 'Violation of terms',
            'metadata' => ['ip' => '127.0.0.1'],
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'admin_id' => $admin->id,
            'action' => 'user.suspend',
            'target_type' => 'user',
            'target_id' => 123,
        ]);
    }

    /**
     * Phase 12 Test: Audit log includes reason
     */
    public function test_audit_log_includes_reason(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@monitly.app',
        ]);

        $reason = 'Spam account detected';

        $log = AuditLog::create([
            'admin_id' => $admin->id,
            'action' => 'user.ban',
            'target_type' => 'user',
            'target_id' => 456,
            'reason' => $reason,
        ]);

        $this->assertEquals($reason, $log->reason);
    }

    /**
     * Phase 12 Test: Owner can access all admin routes
     */
    public function test_owner_can_access_all_admin_routes(): void
    {
        $owner = User::factory()->create([
            'email' => 'admin@monitly.app',
        ]);

        $routes = [
            '/admin',
            '/admin/revenue',
            '/admin/subscriptions',
            '/admin/users',
            '/admin/teams',
            '/admin/queues',
            '/admin/jobs/failed',
            '/admin/webhooks/paddle',
            '/admin/audit',
        ];

        foreach ($routes as $route) {
            $response = $this->actingAs($owner)->get($route);
            $this->assertNotEquals(
                403,
                $response->status(),
                "Owner should have access to {$route}"
            );
        }
    }

    /**
     * Phase 12 Test: Multiple admin actions create multiple audit logs
     */
    public function test_multiple_actions_create_multiple_logs(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@monitly.app',
        ]);

        AuditLog::create([
            'admin_id' => $admin->id,
            'action' => 'user.suspend',
            'target_type' => 'user',
            'target_id' => 1,
            'reason' => 'Reason 1',
        ]);

        AuditLog::create([
            'admin_id' => $admin->id,
            'action' => 'user.unsuspend',
            'target_type' => 'user',
            'target_id' => 1,
            'reason' => 'Reason 2',
        ]);

        AuditLog::create([
            'admin_id' => $admin->id,
            'action' => 'webhook.replay',
            'target_type' => 'webhook',
            'target_id' => 99,
            'reason' => 'Reason 3',
        ]);

        $this->assertEquals(3, AuditLog::where('admin_id', $admin->id)->count());
    }
}
