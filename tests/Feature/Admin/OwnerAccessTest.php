<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_access_admin(): void
    {
        config(['admin.owner_email' => 'owner@example.com']);

        $owner = User::factory()->create(['email' => 'owner@example.com']);

        $response = $this->actingAs($owner)->get('/admin');

        $response->assertStatus(200);
    }

    public function test_non_owner_cannot_access_admin(): void
    {
        config(['admin.owner_email' => 'owner@example.com']);

        $user = User::factory()->create(['email' => 'user@example.com']);

        $response = $this->actingAs($user)->get('/admin');

        $response->assertStatus(403);
    }
}
