<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_admin_routes(): void
    {
        $u = User::factory()->create(['is_admin' => false]);

        $this->actingAs($u)->get('/admin')->assertStatus(403);
        $this->actingAs($u)->get('/admin/users')->assertStatus(403);
    }

    public function test_admin_can_access_admin_routes(): void
    {
        $u = User::factory()->create(['is_admin' => true]);

        $this->actingAs($u)->get('/admin')->assertStatus(200);
        $this->actingAs($u)->get('/admin/users')->assertStatus(200);
        $this->actingAs($u)->get('/admin/system')->assertStatus(200);
    }
}