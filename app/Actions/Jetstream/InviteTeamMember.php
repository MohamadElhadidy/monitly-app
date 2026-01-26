<?php

namespace App\Actions\Jetstream;

use App\Services\Billing\PlanLimits;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Jetstream\Contracts\InvitesTeamMembers;

class InviteTeamMember implements InvitesTeamMembers
{
    public function invite($user, $team, string $email, ?string $role = null)
    {
        Gate::forUser($user)->authorize('addTeamMember', $team);

        $plan = (string) ($team->billing_plan ?: PlanLimits::PLAN_FREE);

        if (! PlanLimits::canInviteMembers($plan)) {
            throw ValidationException::withMessages([
                'email' => 'Invitations are disabled on your plan. Upgrade to Team to invite members.',
            ]);
        }

        $seatLimit = PlanLimits::seatLimitForTeam($team);

        $currentSeats = $team->allUsers()->count();

        $pendingInvites = method_exists($team, 'teamInvitations')
            ? $team->teamInvitations()->count()
            : 0;

        if (($currentSeats + $pendingInvites) >= $seatLimit) {
            throw ValidationException::withMessages([
                'email' => "Seat limit reached ({$seatLimit}). Add Extra Team Member Packs (+3) to invite more.",
            ]);
        }

        Validator::make([
            'email' => $email,
            'role' => $role,
        ], [
            'email' => ['required', 'email', 'max:255'],
            'role' => ['nullable', 'string', 'max:50'],
        ])->validate();

        // Use Jetstream's default invite system (team_invitations table)
        $team->inviteTeamMember($email, $role ?? 'member');
    }
}