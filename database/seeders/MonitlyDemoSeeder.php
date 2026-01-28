<?php

namespace Database\Seeders;

use App\Models\Incident;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\Team;
use App\Models\User;
use App\Models\WebhookEndpoint;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MonitlyDemoSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::query()->firstOrCreate(
            ['email' => 'owner@monitly.test'],
            [
                'name' => 'Monitly Owner',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $member = User::query()->firstOrCreate(
            ['email' => 'member@monitly.test'],
            [
                'name' => 'Team Member',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $team = Team::query()->firstOrCreate(
            ['name' => 'Acme Ops', 'user_id' => $owner->id],
            ['personal_team' => false]
        );

        $team->users()->syncWithoutDetaching([
            $owner->id => ['role' => 'admin'],
            $member->id => ['role' => 'member'],
        ]);


        WebhookEndpoint::factory()
            ->count(2)
            ->forTeam($team)
            ->state(fn () => [
                'secret' => Str::random(40),
                'enabled' => true,
                'retry_meta' => ['attempts' => 0, 'next_retry_at' => null],
            ])
            ->create();

        $individualMonitors = Monitor::factory()
            ->count(1)
            ->individual($owner)
            ->state(fn () => [
                'paused' => false,
                'last_status' => 'up',
                'next_check_at' => now()->addMinutes(5),
                'is_public' => false,
            ])
            ->create();

        foreach ($individualMonitors as $monitor) {
            MonitorCheck::factory()
                ->count(20)
                ->forMonitor($monitor)
                ->create();
        }

 
    }
}
