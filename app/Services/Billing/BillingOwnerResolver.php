<?php

namespace App\Services\Billing;

use App\Models\Team;
use App\Models\User;
use App\Services\Billing\PlanLimits;

class BillingOwnerResolver
{
    public function resolve(User $user): array
    {
        $team = $this->availableTeam($user);

        if ($team && $this->isTeamPlan($team->billing_plan)) {
            return [
                'billable' => $team,
                'team' => $team,
                'type' => 'team',
            ];
        }

        return [
            'billable' => $user,
            'team' => $team,
            'type' => 'user',
        ];
    }

    public function resolveForPlan(User $user, string $plan): ?array
    {
        if ($this->isTeamPlan($plan)) {
            $team = $this->availableTeam($user);

            if (! $team) {
                return null;
            }

            return [
                'billable' => $team,
                'team' => $team,
                'type' => 'team',
            ];
        }

        return [
            'billable' => $user,
            'team' => null,
            'type' => 'user',
        ];
    }

    public function canManage(User $user, ?Team $team): bool
    {
        if (! $team) {
            return true;
        }

        return $user->ownsTeam($team);
    }

    public function isTeamPlan(?string $plan): bool
    {
        return in_array($plan, [PlanLimits::PLAN_TEAM, PlanLimits::PLAN_BUSINESS], true);
    }

    private function currentTeam(User $user): ?Team
    {
        $team = $user->currentTeam;

        if (! $team || $team->personal_team) {
            return null;
        }

        return $team;
    }

    public function availableTeam(User $user): ?Team
    {
        $team = $this->currentTeam($user);

        if ($team) {
            return $team;
        }

        $owned = $user->ownedTeams()->where('personal_team', false)->first();
        if ($owned) {
            return $owned;
        }

        return $user->allTeams()->where('personal_team', false)->first();
    }
}
