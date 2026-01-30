<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\Admin\AdminActionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSuspendTest extends TestCase
{
    use RefreshDatabase;

    public function test_suspend_and_unsuspend_user(): void
    {
        $user = User::factory()->create();

        $service = app(AdminActionService::class);

        $service->suspendUser($user, 'Suspended for review');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'status' => 'suspended',
        ]);

        $service->unsuspendUser($user, 'Cleared');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'status' => 'active',
        ]);
    }
}
