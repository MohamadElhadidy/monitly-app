<?php

namespace App\Services\Billing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class BillingService
{
    public function current(Model $billable): array
    {
        try {
            $plan = $billable->billing_plan ?? 'free';
            $status = $billable->billing_status ?? 'free';

            return [
                'plan' => $plan,
                'status' => $status,
                'subscribed' => in_array($status, ['active']),
                'next_bill_at' => $billable->next_bill_at ?? null,
                'grace_ends_at' => $billable->grace_ends_at ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('Billing service error', ['error' => $e->getMessage()]);
            return ['plan' => 'free', 'status' => 'error', 'subscribed' => false];
        }
    }

    public function can(Model $billable, string $action): bool
    {
        $plan = $billable->billing_plan ?? 'free';

        return match ($action) {
            'create_monitor' => $plan !== 'free',
            'use_slack' => in_array($plan, ['team']),
            'use_webhooks' => in_array($plan, ['team']),
            'invite_users' => in_array($plan, ['team']),
            default => false,
        };
    }
}