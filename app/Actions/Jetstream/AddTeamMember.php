<?php

namespace App\Actions\Jetstream;

use App\Services\Billing\PlanLimits;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Laravel\Jetstream\Contracts\AddsTeamMembers;

class AddTeamMember implements AddsTeamMembers
{
    public function add($user, $team, string $email, ?string $role = null)
    {
        Gate::forUser($user)->authorize('addTeamMember', $team);

        $plan = (string) ($team->billing_plan ?: PlanLimits::PLAN_FREE);

        if (! PlanLimits::canInviteMembers($plan)) {
            throw ValidationException::withMessages([
                'email' => 'Adding members is disabled on your plan. Upgrade to Team to add members.',
            ]);
        }

        $seatLimit = PlanLimits::seatLimitForTeam($team);

        if ($team->allUsers()->count() >= $seatLimit) {
            throw ValidationException::withMessages([
                'email' => "Seat limit reached ({$seatLimit}). Add Extra Team Member Packs (+3) to add more.",
            ]);
        }

        // Jetstream typically resolves the user by email and attaches to the team_user pivot.
        $newMember = \App\Models\User::query()->where('email', $email)->first();

        if (! $newMember) {
            throw ValidationException::withMessages([
                'email' => 'User not found. The user must sign up before being added.',
            ]);
        }

        if ($team->allUsers()->contains('id', $newMember->id)) {
            throw ValidationException::withMessages([
                'email' => 'This user is already a member of the team.',
            ]);
        }

        $team->users()->attach($newMember->id, ['role' => $role ?? 'member']);
    }
}