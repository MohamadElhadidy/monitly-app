<?php

namespace Database\Factories;

use App\Models\Monitor;
use App\Models\MonitorMemberPermission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MonitorMemberPermission>
 */
class MonitorMemberPermissionFactory extends Factory
{
    protected $model = MonitorMemberPermission::class;

    public function definition(): array
    {
        return [
            'monitor_id' => Monitor::factory(),
            'user_id' => User::factory(),
            'view_logs' => $this->faker->boolean(60),
            'receive_alerts' => $this->faker->boolean(60),
            'pause_resume' => $this->faker->boolean(30),
            'edit_settings' => $this->faker->boolean(20),
        ];
    }

    public function forMonitor(Monitor $monitor): self
    {
        return $this->state(fn () => ['monitor_id' => $monitor->id]);
    }

    public function forUser(User $user): self
    {
        return $this->state(fn () => ['user_id' => $user->id]);
    }
}
