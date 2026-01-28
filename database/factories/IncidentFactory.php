<?php

namespace Database\Factories;

use App\Models\Incident;
use App\Models\Monitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Incident>
 */
class IncidentFactory extends Factory
{
    protected $model = Incident::class;

    public function definition(): array
    {
        $started = now()->subHours($this->faker->numberBetween(1, 72));
        $recovered = $this->faker->boolean(70) ? (clone $started)->addMinutes($this->faker->numberBetween(2, 120)) : null;
        $downtime = $recovered ? $started->diffInSeconds($recovered) : 0;

        return [
            'monitor_id' => Monitor::factory(),
            'started_at' => $started,
            'recovered_at' => $recovered,
            'downtime_seconds' => $downtime,
            'cause_summary' => $this->faker->randomElement([
                'Timeouts from upstream',
                'DNS resolution failure',
                'HTTP 5xx from origin',
                'TLS handshake failure',
            ]),
            'created_by' => 'system',
            'sla_counted' => true,
        ];
    }

    public function open(): self
    {
        return $this->state(fn () => [
            'recovered_at' => null,
            'downtime_seconds' => 0,
        ]);
    }

    public function forMonitor(Monitor $monitor): self
    {
        return $this->state(fn () => ['monitor_id' => $monitor->id]);
    }
}
