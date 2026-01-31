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
    public const PLAN_BUSINESS = 'business';

    public const BILLING_STATUS_FREE = 'free';
    public const BILLING_STATUS_ACTIVE = 'active';
    public const BILLING_STATUS_PAST_DUE = 'past_due';
    public const BILLING_STATUS_CANCELING = 'canceling';
    public const BILLING_STATUS_CANCELED = 'canceled';

    public const VALID_BILLING_STATUSES = [
        self::BILLING_STATUS_FREE,
        self::BILLING_STATUS_ACTIVE,
        self::BILLING_STATUS_PAST_DUE,
        self::BILLING_STATUS_CANCELING,
        self::BILLING_STATUS_CANCELED,
    ];

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
            self::PLAN_FREE => 3,
            self::PLAN_PRO => 15,
            self::PLAN_TEAM => 50,
            self::PLAN_BUSINESS => 150,
            default => 3,
        };
    }

    public static function baseSeatLimit(string $plan): int
    {
        return match ($plan) {
            self::PLAN_TEAM => 5,
            self::PLAN_BUSINESS => 15,
            default => 1,
        };
    }

    public static function baseIntervalMinutes(string $plan): int
    {
        return match ($plan) {
            self::PLAN_FREE => 15,
            self::PLAN_PRO => 10,
            self::PLAN_TEAM => 10,
            self::PLAN_BUSINESS => 5,
            default => 15,
        };
    }

    public static function checksRetentionDays(string $plan): int
    {
        return match ($plan) {
            self::PLAN_FREE => 1,
            self::PLAN_PRO => 30,
            self::PLAN_TEAM => 90,
            self::PLAN_BUSINESS => 365,
            default => 1,
        };
    }

    public static function incidentsRetentionDays(string $plan): int
    {
        return match ($plan) {
            self::PLAN_FREE => 7,
            self::PLAN_PRO => 90,
            self::PLAN_TEAM => 180,
            self::PLAN_BUSINESS => 365,
            default => 7,
        };
    }

    public static function notificationLogsRetentionDays(string $plan): int
    {
        return match ($plan) {
            self::PLAN_FREE => 1,
            self::PLAN_PRO => 30,
            self::PLAN_TEAM => 90,
            self::PLAN_BUSINESS => 180,
            default => 1,
        };
    }

    public static function historyDays(?string $plan): ?int
    {
        return self::checksRetentionDays($plan ?? self::PLAN_FREE);
    }

    public static function canInviteMembers(string $plan): bool
    {
        return in_array($plan, [self::PLAN_TEAM, self::PLAN_BUSINESS], true);
    }

    public static function canUseSlack(string $plan): bool
    {
        return in_array($plan, [self::PLAN_TEAM, self::PLAN_BUSINESS], true);
    }

    public static function canUseWebhooks(string $plan): bool
    {
        return in_array($plan, [self::PLAN_TEAM, self::PLAN_BUSINESS], true);
    }

    public static function canUseSla(string $plan): bool
    {
        return in_array($plan, [self::PLAN_PRO, self::PLAN_TEAM, self::PLAN_BUSINESS], true);
    }

    public static function canExportPdf(string $plan): bool
    {
        return in_array($plan, [self::PLAN_PRO, self::PLAN_TEAM, self::PLAN_BUSINESS], true);
    }

    public static function isNormalUserPlan(string $plan): bool
    {
        return in_array($plan, [self::PLAN_FREE, self::PLAN_PRO], true);
    }

    public static function isTeamPlan(string $plan): bool
    {
        return in_array($plan, [self::PLAN_TEAM, self::PLAN_BUSINESS], true);
    }

    public static function isSubscribed(string $status): bool
    {
        return in_array($status, [
            self::BILLING_STATUS_ACTIVE,
            self::BILLING_STATUS_PAST_DUE,
            self::BILLING_STATUS_CANCELING,
        ], true);
    }

    public static function monitorLimitForUser(User $user): int
    {
        $plan = (string) ($user->billing_plan ?: self::PLAN_FREE);
        return self::baseMonitorLimit($plan);
    }

    public static function effectiveIntervalMinutesForUser(User $user): int
    {
        $plan = (string) ($user->billing_plan ?: self::PLAN_FREE);
        return self::baseIntervalMinutes($plan);
    }

    public static function planForTeam(Team $team): string
    {
        $plan = (string) ($team->billing_plan ?: self::PLAN_FREE);
        return in_array($plan, [self::PLAN_TEAM, self::PLAN_BUSINESS], true) ? $plan : self::PLAN_FREE;
    }

    public static function monitorLimitForTeam(Team $team): int
    {
        $plan = self::planForTeam($team);
        return self::baseMonitorLimit($plan);
    }

    public static function seatLimitForTeam(Team $team): int
    {
        $plan = self::planForTeam($team);
        return self::baseSeatLimit($plan);
    }

    public static function effectiveIntervalMinutesForTeam(Team $team): int
    {
        $plan = self::planForTeam($team);
        return self::baseIntervalMinutes($plan);
    }

    public static function checksRetentionForUser(User $user): int
    {
        $plan = (string) ($user->billing_plan ?: self::PLAN_FREE);
        return self::checksRetentionDays($plan);
    }

    public static function checksRetentionForTeam(Team $team): int
    {
        $plan = self::planForTeam($team);
        return self::checksRetentionDays($plan);
    }

    public static function incidentsRetentionForUser(User $user): int
    {
        $plan = (string) ($user->billing_plan ?: self::PLAN_FREE);
        return self::incidentsRetentionDays($plan);
    }

    public static function incidentsRetentionForTeam(Team $team): int
    {
        $plan = self::planForTeam($team);
        return self::incidentsRetentionDays($plan);
    }
}
