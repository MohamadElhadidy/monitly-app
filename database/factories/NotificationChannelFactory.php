<?php

namespace Database\Factories;

use App\Models\NotificationChannel;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationChannel>
 */
class NotificationChannelFactory extends Factory
{
    protected $model = NotificationChannel::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'email_enabled' => true,
            'slack_enabled' => $this->faker->boolean(60),
            'slack_webhook_url' => $this->faker->boolean(60) ? 'https://hooks.slack.com/services/T000/B000/XXXX' : null,
            'webhooks_enabled' => $this->faker->boolean(60),
        ];
    }

    public function forTeam(Team $team): self
    {
        return $this->state(fn () => ['team_id' => $team->id]);
    }
}
