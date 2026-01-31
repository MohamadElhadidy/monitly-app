<?php

namespace Tests\Feature\Monitors;

use App\Models\Monitor;
use App\Models\Team;
use App\Models\User;
use App\Services\Billing\PlanEnforcer;
use App\Services\Billing\PlanLimits;
use App\Services\Security\SsrfBlockedException;
use App\Services\Security\SsrfGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MonitorLimitsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Phase 5 Test: SSRF blocks localhost
     */
    public function test_ssrf_blocks_localhost(): void
    {
        $guard = new SsrfGuard();

        $this->expectException(SsrfBlockedException::class);
        $this->expectExceptionMessage('Localhost is blocked');

        $guard->validateUrl('http://localhost/test');
    }

    /**
     * Phase 5 Test: SSRF blocks 127.0.0.1
     */
    public function test_ssrf_blocks_loopback_ip(): void
    {
        $guard = new SsrfGuard();

        $this->expectException(SsrfBlockedException::class);

        $guard->validateUrl('http://127.0.0.1/test');
    }

    /**
     * Phase 5 Test: SSRF blocks private IP ranges (10.x.x.x)
     */
    public function test_ssrf_blocks_private_10_range(): void
    {
        $guard = new SsrfGuard();

        $this->expectException(SsrfBlockedException::class);

        $guard->validateUrl('http://10.0.0.1/test');
    }

    /**
     * Phase 5 Test: SSRF blocks private IP ranges (192.168.x.x)
     */
    public function test_ssrf_blocks_private_192_range(): void
    {
        $guard = new SsrfGuard();

        $this->expectException(SsrfBlockedException::class);

        $guard->validateUrl('http://192.168.1.1/test');
    }

    /**
     * Phase 5 Test: SSRF blocks private IP ranges (172.16.x.x)
     */
    public function test_ssrf_blocks_private_172_range(): void
    {
        $guard = new SsrfGuard();

        $this->expectException(SsrfBlockedException::class);

        $guard->validateUrl('http://172.16.0.1/test');
    }

    /**
     * Phase 5 Test: SSRF blocks metadata endpoints
     */
    public function test_ssrf_blocks_metadata_endpoint(): void
    {
        $guard = new SsrfGuard();

        $this->expectException(SsrfBlockedException::class);

        $guard->validateUrl('http://169.254.169.254/latest/meta-data');
    }

    /**
     * Phase 5 Test: SSRF only allows http/https
     */
    public function test_ssrf_only_allows_http_https(): void
    {
        $guard = new SsrfGuard();

        $this->expectException(SsrfBlockedException::class);
        $this->expectExceptionMessage('Only http/https URLs are allowed');

        $guard->validateUrl('ftp://example.com/file');
    }

    /**
     * Phase 5 Test: SSRF blocks credentials in URL
     */
    public function test_ssrf_blocks_credentials_in_url(): void
    {
        $guard = new SsrfGuard();

        $this->expectException(SsrfBlockedException::class);
        $this->expectExceptionMessage('Credentials in URL are not allowed');

        $guard->validateUrl('http://user:pass@example.com/');
    }

    /**
     * Phase 5 Test: Monitor creation is blocked when at plan limit
     */
    public function test_monitor_creation_blocked_at_plan_limit(): void
    {
        $user = User::factory()->create([
            'billing_plan' => PlanLimits::PLAN_FREE,
            'billing_status' => 'free',
        ]);

        $limit = PlanLimits::baseMonitorLimit(PlanLimits::PLAN_FREE);
        $this->assertEquals(3, $limit);

        for ($i = 1; $i <= $limit; $i++) {
            Monitor::factory()->create([
                'user_id' => $user->id,
                'team_id' => null,
                'url' => "https://example{$i}.com",
                'name' => "Monitor {$i}",
            ]);
        }

        $enforcer = app(PlanEnforcer::class);

        $this->expectException(ValidationException::class);

        $newMonitor = new Monitor([
            'user_id' => $user->id,
            'team_id' => null,
            'url' => 'https://blocked.com',
            'name' => 'Should Be Blocked',
        ]);

        $enforcer->assertCanCreateMonitor($newMonitor);
    }

    /**
     * Phase 5 Test: Over limit blocking locks extra monitors
     */
    public function test_over_limit_blocks_newest_monitors(): void
    {
        $user = User::factory()->create([
            'billing_plan' => PlanLimits::PLAN_FREE,
            'billing_status' => 'free',
        ]);

        $limit = PlanLimits::baseMonitorLimit(PlanLimits::PLAN_FREE);

        for ($i = 1; $i <= $limit + 2; $i++) {
            Monitor::factory()->create([
                'user_id' => $user->id,
                'team_id' => null,
                'url' => "https://example{$i}.com",
                'name' => "Monitor {$i}",
                'created_at' => now()->subMinutes($limit + 3 - $i),
            ]);
        }

        app(PlanEnforcer::class)->enforceMonitorCapForUser($user);

        $monitors = Monitor::where('user_id', $user->id)
            ->whereNull('team_id')
            ->orderBy('created_at')
            ->get();

        $unlockedCount = $monitors->where('locked_by_plan', false)->count();
        $lockedCount = $monitors->where('locked_by_plan', true)->count();

        $this->assertEquals($limit, $unlockedCount);
        $this->assertEquals(2, $lockedCount);
    }

    /**
     * Phase 5 Test: Team monitor limit enforced
     */
    public function test_team_monitor_limit_enforced(): void
    {
        $owner = User::factory()->create();

        $team = Team::factory()->create([
            'user_id' => $owner->id,
            'personal_team' => false,
            'billing_plan' => PlanLimits::PLAN_TEAM,
            'billing_status' => 'active',
        ]);

        $limit = PlanLimits::monitorLimitForTeam($team);
        $this->assertEquals(50, $limit);

        for ($i = 1; $i <= 52; $i++) {
            Monitor::factory()->create([
                'user_id' => $owner->id,
                'team_id' => $team->id,
                'url' => "https://team-example{$i}.com",
                'name' => "Team Monitor {$i}",
                'created_at' => now()->subMinutes(53 - $i),
            ]);
        }

        app(PlanEnforcer::class)->enforceMonitorCapForTeam($team);

        $lockedCount = Monitor::where('team_id', $team->id)
            ->where('locked_by_plan', true)
            ->count();

        $this->assertEquals(2, $lockedCount);
    }

    /**
     * Phase 5 Test: Pro plan has 15 monitor limit
     */
    public function test_pro_plan_has_15_monitor_limit(): void
    {
        $this->assertEquals(15, PlanLimits::baseMonitorLimit(PlanLimits::PLAN_PRO));
    }

    /**
     * Phase 5 Test: Team plan has 50 monitor limit
     */
    public function test_team_plan_has_50_monitor_limit(): void
    {
        $this->assertEquals(50, PlanLimits::baseMonitorLimit(PlanLimits::PLAN_TEAM));
    }

    /**
     * Phase 5 Test: Business plan has 150 monitor limit
     */
    public function test_business_plan_has_150_monitor_limit(): void
    {
        $this->assertEquals(150, PlanLimits::baseMonitorLimit(PlanLimits::PLAN_BUSINESS));
    }

    /**
     * Phase 5 Test: Monitor states include all required values
     */
    public function test_monitor_has_required_states(): void
    {
        $monitor = Monitor::factory()->create([
            'last_status' => 'pending',
        ]);

        $validStates = ['pending', 'up', 'degraded', 'down', 'paused', 'blocked', 'unknown'];

        foreach ($validStates as $state) {
            $monitor->last_status = $state;
            $monitor->save();

            $this->assertEquals($state, $monitor->fresh()->last_status);
        }
    }
}
