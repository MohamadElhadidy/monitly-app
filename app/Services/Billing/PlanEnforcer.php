<?php

namespace App\Services\Billing;

use App\Models\Monitor;
use App\Models\Team;
use App\Models\User;
use App\Services\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlanEnforcer
{
    public function assertCanCreateMonitor(Monitor $monitor): void
    {
        $owner = User::query()->find($monitor->user_id);
        if (! $owner) {
            throw ValidationException::withMessages(['monitor' => 'Invalid monitor owner.']);
        }

        if ($monitor->team_id) {
            $team = Team::query()->find($monitor->team_id);
            if (! $team) {
                throw ValidationException::withMessages(['team' => 'Team not found.']);
            }

            if (PlanLimits::planForTeam($team) !== PlanLimits::PLAN_TEAM) {
                throw ValidationException::withMessages([
                    'team' => 'Your team is not on the Team plan. Team monitors are not allowed.',
                ]);
            }

            $limit = PlanLimits::monitorLimitForTeam($team);
            $used = Monitor::query()->where('team_id', $team->id)->count();

            if ($used >= $limit) {
                throw ValidationException::withMessages([
                    'limit' => "Monitor limit reached ({$limit}). Upgrade or add Extra Monitor Packs (+5).",
                ]);
            }

            return;
        }

        $plan = (string) ($owner->billing_plan ?: PlanLimits::PLAN_FREE);

        if (! in_array($plan, [PlanLimits::PLAN_FREE, PlanLimits::PLAN_PRO], true)) {
            $plan = PlanLimits::PLAN_FREE;
        }

        $limit = PlanLimits::monitorLimitForUser($owner);
        $used = Monitor::query()
            ->where('user_id', $owner->id)
            ->whereNull('team_id')
            ->count();

        if ($used >= $limit) {
            throw ValidationException::withMessages([
                'limit' => "Monitor limit reached ({$limit}). Upgrade or add Extra Monitor Packs (+5) on Pro.",
            ]);
        }
    }

    public function assertCanResumeMonitor(Monitor $monitor): void
    {
        if ($monitor->locked_by_plan) {
            throw ValidationException::withMessages([
                'paused' => 'This monitor is locked by your plan. Upgrade to unlock.',
            ]);
        }
    }

    public function enforceGraceDowngrades(): void
    {
        $now = now();

        User::query()
            ->where('billing_status', 'grace')
            ->whereNotNull('grace_ends_at')
            ->where('grace_ends_at', '<=', $now)
            ->chunkById(200, function ($users) {
                foreach ($users as $u) {
                    $this->downgradeUserToFree($u);
                }
            });

        Team::query()
            ->where('billing_status', 'grace')
            ->whereNotNull('grace_ends_at')
            ->where('grace_ends_at', '<=', $now)
            ->chunkById(200, function ($teams) {
                foreach ($teams as $t) {
                    $this->downgradeTeamToFree($t);
                }
            });
    }

    public function downgradeUserToFree(User $user): void
    {
        DB::transaction(function () use ($user) {
            $before = [
                'plan' => $user->billing_plan,
                'status' => $user->billing_status,
                'grace_ends_at' => $user->grace_ends_at?->toIso8601String(),
            ];

            $user->billing_plan = PlanLimits::PLAN_FREE;
            $user->billing_status = 'free';
            $user->addon_extra_monitor_packs = 0;
            $user->addon_interval_override_minutes = null;
            $user->grace_ends_at = null;
            $user->save();

            $this->enforceMonitorCapForUser($user);

            Audit::log(
                action: 'billing.downgraded_after_grace',
                subject: $user,
                teamId: null,
                meta: ['before' => $before, 'after' => ['plan' => $user->billing_plan, 'status' => $user->billing_status]],
                actorType: 'system',
                actorId: null
            );
        });
    }

    public function downgradeTeamToFree(Team $team): void
    {
        DB::transaction(function () use ($team) {
            $before = [
                'plan' => $team->billing_plan,
                'status' => $team->billing_status,
                'grace_ends_at' => $team->grace_ends_at?->toIso8601String(),
            ];

            $team->billing_plan = PlanLimits::PLAN_FREE;
            $team->billing_status = 'free';
            $team->addon_extra_monitor_packs = 0;
            $team->addon_extra_seat_packs = 0;
            $team->addon_interval_override_minutes = null;
            $team->grace_ends_at = null;
            $team->save();

            $this->enforceSeatCapForTeam($team);
            $this->enforceMonitorCapForTeam($team);

            Audit::log(
                action: 'billing.downgraded_after_grace',
                subject: $team,
                teamId: (int) $team->id,
                meta: ['before' => $before, 'after' => ['plan' => $team->billing_plan, 'status' => $team->billing_status]],
                actorType: 'system',
                actorId: null
            );
        });
    }

    public function enforceMonitorCapForUser(User $user): void
    {
        $limit = PlanLimits::monitorLimitForUser($user);

        $q = Monitor::query()
            ->where('user_id', $user->id)
            ->whereNull('team_id')
            ->orderBy('created_at', 'asc');

        $allowedIds = $q->limit($limit)->pluck('id')->all();

        Monitor::query()
            ->where('user_id', $user->id)
            ->whereNull('team_id')
            ->whereIn('id', $allowedIds)
            ->update([
                'locked_by_plan' => false,
                'locked_reason' => null,
            ]);

        Monitor::query()
            ->where('user_id', $user->id)
            ->whereNull('team_id')
            ->whereNotIn('id', $allowedIds)
            ->update([
                'paused' => true,
                'locked_by_plan' => true,
                'locked_reason' => 'Plan limit exceeded',
            ]);
    }

    public function enforceMonitorCapForTeam(Team $team): void
    {
        $limit = PlanLimits::monitorLimitForTeam($team);

        $q = Monitor::query()
            ->where('team_id', $team->id)
            ->orderBy('created_at', 'asc');

        $allowedIds = $q->limit($limit)->pluck('id')->all();

        Monitor::query()
            ->where('team_id', $team->id)
            ->whereIn('id', $allowedIds)
            ->update([
                'locked_by_plan' => false,
                'locked_reason' => null,
            ]);

        Monitor::query()
            ->where('team_id', $team->id)
            ->whereNotIn('id', $allowedIds)
            ->update([
                'paused' => true,
                'locked_by_plan' => true,
                'locked_reason' => 'Plan limit exceeded',
            ]);
    }

    public function enforceSeatCapForTeam(Team $team): void
    {
        $plan = PlanLimits::planForTeam($team);

        if (! PlanLimits::canInviteMembers($plan)) {
            if (method_exists($team, 'teamInvitations')) {
                $team->teamInvitations()->delete();
            }
        }

        $limit = PlanLimits::seatLimitForTeam($team);

        $pivotUsers = $team->users()->get(['users.id'])->pluck('id')->all();
        $ownerId = (int) $team->user_id;

        $allUserIds = collect($pivotUsers)->push($ownerId)->unique()->values()->all();

        if (count($allUserIds) <= $limit) {
            return;
        }

        $toRemoveCount = count($allUserIds) - $limit;

        $removeIds = $team->users()
            ->orderByDesc('team_user.created_at')
            ->limit($toRemoveCount)
            ->pluck('users.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (! empty($removeIds)) {
            $team->users()->detach($removeIds);
        }
    }
}