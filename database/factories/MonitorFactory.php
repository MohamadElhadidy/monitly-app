<?php

namespace Database\Factories;

use App\Models\Monitor;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Monitor>
 */
class MonitorFactory extends Factory
{
    protected $model = Monitor::class;

    public function definition(): array
    {
        $status = $this->faker->randomElement(['unknown', 'up', 'down', 'degraded']);

        return [
            'team_id' => null,
            'user_id' => User::factory(),
            'name' => $this->faker->words(2, true),
            'url' => $this->faker->url(),
            'is_public' => $this->faker->boolean(30),
            'paused' => $this->faker->boolean(10),
            'last_status' => $status,
            'consecutive_failures' => $status === 'down' ? $this->faker->numberBetween(1, 3) : 0,
            'next_check_at' => now()->addMinutes($this->faker->numberBetween(1, 15)),
        ];
    }

    public function forTeam(Team $team, ?User $owner = null): self
    {
        return $this->state(function () use ($team, $owner) {
            return [
                'team_id' => $team->id,
                'user_id' => ($owner?->id) ?? $team->user_id,
            ];
        });
    }

    public function individual(User $user): self
    {
        return $this->state(fn () => [
            'team_id' => null,
            'user_id' => $user->id,
        ]);
    }
}
