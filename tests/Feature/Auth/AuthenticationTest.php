<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Phase 1 Test: Guest is redirected from /app to /login
     */
    public function test_guest_redirected_from_app_to_login(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    /**
     * Phase 1 Test: Guest is redirected from monitors to login
     */
    public function test_guest_redirected_from_monitors_to_login(): void
    {
        $response = $this->get('/app/monitors');

        $response->assertRedirect('/login');
    }

    /**
     * Phase 1 Test: Guest is redirected from billing to login
     */
    public function test_guest_redirected_from_billing_to_login(): void
    {
        $response = $this->get('/app/billing');

        $response->assertRedirect('/login');
    }

    /**
     * Phase 1 Test: Logged-in user can access dashboard
     */
    public function test_logged_in_user_can_access_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
    }

    /**
     * Phase 1 Test: Logged-in user can access monitors
     */
    public function test_logged_in_user_can_access_monitors(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/app/monitors');

        $response->assertStatus(200);
    }

    /**
     * Phase 1 Test: Logged-in user can access billing
     */
    public function test_logged_in_user_can_access_billing(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/app/billing');

        $response->assertStatus(200);
    }

    /**
     * Phase 1 Test: Login page is accessible
     */
    public function test_login_page_is_accessible(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    /**
     * Phase 1 Test: Register page is accessible
     */
    public function test_register_page_is_accessible(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    /**
     * Phase 1 Test: User can logout
     */
    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
    }
}
