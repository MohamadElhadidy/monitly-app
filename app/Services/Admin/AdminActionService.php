<?php

namespace App\Services\Admin;

use App\Models\Team;
use App\Models\User;
use App\Services\Audit\Audit;
use Illuminate\Support\Facades\DB;

class AdminActionService
{
    public function suspendUser(User $user, string $reason): void
    {
        $user->forceFill([
            'status' => 'suspended',
            'suspended_at' => now(),
            'suspended_reason' => $reason,
        ])->save();

        Audit::log('admin.user.suspended', $user, null, ['reason' => $reason]);
    }

    public function unsuspendUser(User $user, string $reason): void
    {
        $user->forceFill([
            'status' => 'active',
            'suspended_at' => null,
            'suspended_reason' => null,
        ])->save();

        Audit::log('admin.user.unsuspended', $user, null, ['reason' => $reason]);
    }

    public function banUser(User $user, string $reason): void
    {
        $user->forceFill([
            'status' => 'banned',
            'banned_at' => now(),
            'ban_reason' => $reason,
        ])->save();

        Audit::log('admin.user.banned', $user, null, ['reason' => $reason]);
    }

    public function forceLogoutUser(User $user, string $reason): void
    {
        DB::table('sessions')->where('user_id', $user->id)->delete();

        Audit::log('admin.user.force_logout', $user, null, ['reason' => $reason]);
    }

    public function restrictUser(User $user, string $reason): void
    {
        $user->forceFill([
            'status' => 'restricted',
            'restricted_at' => now(),
            'restricted_reason' => $reason,
        ])->save();

        Audit::log('admin.user.restricted', $user, null, ['reason' => $reason]);
    }

    public function suspendTeam(Team $team, string $reason): void
    {
        $team->forceFill([
            'status' => 'suspended',
            'suspended_at' => now(),
            'suspended_reason' => $reason,
        ])->save();

        Audit::log('admin.team.suspended', $team, $team->id, ['reason' => $reason]);
    }

    public function unsuspendTeam(Team $team, string $reason): void
    {
        $team->forceFill([
            'status' => 'active',
            'suspended_at' => null,
            'suspended_reason' => null,
        ])->save();

        Audit::log('admin.team.unsuspended', $team, $team->id, ['reason' => $reason]);
    }

    public function banTeam(Team $team, string $reason): void
    {
        $team->forceFill([
            'status' => 'banned',
            'banned_at' => now(),
            'ban_reason' => $reason,
        ])->save();

        Audit::log('admin.team.banned', $team, $team->id, ['reason' => $reason]);
    }

    public function enforceTeamLimits(Team $team, string $reason): void
    {
        $team->forceFill([
            'status' => 'restricted',
            'restricted_at' => now(),
            'restricted_reason' => $reason,
        ])->save();

        Audit::log('admin.team.enforce_limits', $team, $team->id, ['reason' => $reason]);
    }

    public function restrictTeam(Team $team, string $reason): void
    {
        $team->forceFill([
            'status' => 'restricted',
            'restricted_at' => now(),
            'restricted_reason' => $reason,
        ])->save();

        Audit::log('admin.team.restricted', $team, $team->id, ['reason' => $reason]);
    }
}
