<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebhookEndpoint>
 */
class WebhookEndpointFactory extends Factory
{
    protected $model = WebhookEndpoint::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'url' => $this->faker->url(),
            'secret' => Str::random(40),
            'enabled' => $this->faker->boolean(85),
            'last_error' => null,
            'retry_meta' => [
                'attempts' => 0,
                'next_retry_at' => null,
            ],
        ];
    }

    public function forTeam(Team $team): self
    {
        return $this->state(fn () => ['team_id' => $team->id]);
    }
}
