<?php

namespace Tests\Feature;

use App\Models\Monitor;
use App\Models\User;
use App\Services\Sla\MonitorSlaPdfReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SlaPdfReportAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_auth_for_download(): void
    {
        $user = User::factory()->create();
        $monitor = Monitor::query()->create([
            'team_id' => null,
            'user_id' => $user->id,
            'name' => 'M',
            'url' => 'https://example.com',
            'paused' => false,
            'email_alerts_enabled' => true,
            'slack_alerts_enabled' => true,
            'webhook_alerts_enabled' => true,
            'last_status' => 'up',
            'consecutive_failures' => 0,
            'next_check_at' => now(),
        ]);

        $svc = app(MonitorSlaPdfReportService::class);
        $result = $svc->generate($monitor, $user, 30, 30);

        $url = $result['download_url'];

        $this->get($url)->assertRedirect(); // to login
    }

    public function test_signed_url_and_policy_enforced(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $monitor = Monitor::query()->create([
            'team_id' => null,
            'user_id' => $owner->id,
            'name' => 'M',
            'url' => 'https://example.com',
            'paused' => false,
            'email_alerts_enabled' => true,
            'slack_alerts_enabled' => true,
            'webhook_alerts_enabled' => true,
            'last_status' => 'up',
            'consecutive_failures' => 0,
            'next_check_at' => now(),
        ]);

        $svc = app(MonitorSlaPdfReportService::class);
        $result = $svc->generate($monitor, $owner, 30, 30);

        // Owner can download
        $this->actingAs($owner)->get($result['download_url'])->assertOk();

        // Tamper with URL: remove signature -> 403 from signed middleware
        $parsed = parse_url($result['download_url']);
        $path = $parsed['path'] ?? '';
        $badUrl = URL::to($path); // no signature query

        $this->actingAs($owner)->get($badUrl)->assertStatus(403);

        // Other user blocked by policy even if they had the signed URL
        $this->actingAs($other)->get($result['download_url'])->assertStatus(403);
    }
}