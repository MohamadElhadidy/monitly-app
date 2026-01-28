<?php

namespace Database\Factories;

use App\Models\Monitor;
use App\Models\MonitorCheck;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MonitorCheck>
 */
class MonitorCheckFactory extends Factory
{
    protected $model = MonitorCheck::class;

    public function definition(): array
    {
        $ok = $this->faker->boolean(85);

        return [
            'monitor_id' => Monitor::factory(),
            'checked_at' => now()->subMinutes($this->faker->numberBetween(1, 60 * 24)),
            'ok' => $ok,
            'status_code' => $ok ? $this->faker->randomElement([200, 204, 301, 302]) : $this->faker->randomElement([0, 500, 502, 503, 504]),
            'response_time_ms' => $ok ? $this->faker->numberBetween(80, 1200) : $this->faker->numberBetween(200, 5000),
            'error_code' => $ok ? null : $this->faker->randomElement(['TIMEOUT', 'DNS', 'CONNECT', 'HTTP_ERROR']),
            'error_message' => $ok ? null : $this->faker->sentence(),
            'resolved_ip' => $this->faker->ipv4(),
            'resolved_host' => parse_url($this->faker->url(), PHP_URL_HOST),
            'raw_response_meta' => [
                'redirects' => $this->faker->numberBetween(0, 2),
                'tls' => $this->faker->boolean(),
            ],
        ];
    }

    public function forMonitor(Monitor $monitor): self
    {
        return $this->state(fn () => ['monitor_id' => $monitor->id]);
    }
}
