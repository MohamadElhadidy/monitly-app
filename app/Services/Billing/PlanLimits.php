<?php

namespace App\Services\Billing;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Carbon;

class PlanLimits
{
    public const PLAN_FREE = 'free';
    public const PLAN_PRO = 'pro';
    public const PLAN_TEAM = 'team';

    public static function isRefundEligible(?Carbon $firstPaidAt, ?Carbon $refundOverrideUntil, ?Carbon $now = null): bool
    {
        $now = $now ?: now();

        if ($refundOverrideUntil && $refundOverrideUntil->isFuture()) {
            return true;
        }

        if (! $firstPaidAt) return false;

        return $now->lessThanOrEqualTo($firstPaidAt->copy()->addDays(30));
    }

    public static function baseMonitorLimit(string $plan): int
    {
        return match ($plan) {
            self::PLAN_FREE => 1,
            self::PLAN_PRO => 5,
            self::PLAN_TEAM => 20,
            default => 1,
        };
    }

    public static function baseSeatLimit(string $plan): int
    {
        return match ($plan) {
            self::PLAN_TEAM => 5,
            default => 1, // Free/Pro are solo (no invitations)
        };
    }

    public static function baseIntervalMinutes(string $plan): int
    {
        return match ($plan) {
            self::PLAN_FREE => 15,
            self::PLAN_PRO => 5,
            self::PLAN_TEAM => 5,
            default => 15,
        };
    }

    public static function historyDays(?string $plan): ?int
    {
        return match ($plan) {
            self::PLAN_FREE => 7,
            self::PLAN_PRO => null,
            self::PLAN_TEAM => null,
            default => 7,
        };
    }

    public static function canInviteMembers(string $plan): bool
    {
        return $plan === self::PLAN_TEAM;
    }

    public static function canUseSlack(string $plan): bool
    {
        return $plan === self::PLAN_TEAM;
    }

    public static function canUseWebhooks(string $plan): bool
    {
        return $plan === self::PLAN_TEAM;
    }

    public static function monitorLimitForUser(User $user): int
    {
        $plan = (string) ($user->billing_plan ?: self::PLAN_FREE);

        $base = self::baseMonitorLimit($plan);

        $packs = (int) ($user->addon_extra_monitor_packs ?? 0);
        if (! in_array($plan, [self::PLAN_PRO], true)) {
            $packs = 0; // Add-ons only for Pro (users) and Team (teams)
        }

        return $base + ($packs * 5);
    }

    public static function effectiveIntervalMinutesForUser(User $user): int
    {
        $plan = (string) ($user->billing_plan ?: self::PLAN_FREE);
        $base = self::baseIntervalMinutes($plan);

        $override = (int) ($user->addon_interval_override_minutes ?? 0);
        $override = in_array($override, [1, 2], true) ? $override : 0;

        // override allowed for Pro only (user-level)
        if ($plan !== self::PLAN_PRO) {
            $override = 0;
        }

        return $override > 0 ? min($override, $base) : $base;
    }

    public static function planForTeam(Team $team): string
    {
        $plan = (string) ($team->billing_plan ?: self::PLAN_FREE);
        return in_array($plan, [self::PLAN_FREE, self::PLAN_TEAM], true) ? $plan : self::PLAN_FREE;
    }

    public static function monitorLimitForTeam(Team $team): int
    {
        $plan = self::planForTeam($team);

        $base = self::baseMonitorLimit($plan);

        $packs = (int) ($team->addon_extra_monitor_packs ?? 0);
        if ($plan !== self::PLAN_TEAM) {
            $packs = 0;
        }

        return $base + ($packs * 5);
    }

    public static function seatLimitForTeam(Team $team): int
    {
        $plan = self::planForTeam($team);

        $base = self::baseSeatLimit($plan);

        $packs = (int) ($team->addon_extra_seat_packs ?? 0);
        if ($plan !== self::PLAN_TEAM) {
            $packs = 0;
        }

        return $base + ($packs * 3);
    }

    public static function effectiveIntervalMinutesForTeam(Team $team): int
    {
        $plan = self::planForTeam($team);
        $base = self::baseIntervalMinutes($plan);

        $override = (int) ($team->addon_interval_override_minutes ?? 0);
        $override = in_array($override, [1, 2], true) ? $override : 0;

        if ($plan !== self::PLAN_TEAM) {
            $override = 0;
        }

        return $override > 0 ? min($override, $base) : $base;
    }
}