<?php

namespace App\Services\Billing;

use App\Models\User;
use App\Models\Monitor;
use App\Models\BillingInvoice;
use App\Models\BillingTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class BillingService
{
    /**
     * Get current billing information for a user
     */
    public function current(Model $billable): array
    {
        try {
            $plan = $billable->billing_plan ?? 'free';
            $status = $billable->billing_status ?? 'free';

            return [
                'plan' => $plan,
                'status' => $status,
                'subscribed' => in_array($status, ['active', 'past_due', 'canceling']),
                'next_bill_at' => $billable->next_bill_at ?? null,
                'grace_ends_at' => $billable->grace_ends_at ?? null,
                'in_grace_period' => $this->isInGracePeriod($billable),
                'trial_ends_at' => $billable->trial_ends_at ?? null,
                'on_trial' => $this->isOnTrial($billable),
            ];
        } catch (\Exception $e) {
            Log::error('Billing service error', ['error' => $e->getMessage()]);
            return ['plan' => 'free', 'status' => 'error', 'subscribed' => false];
        }
    }

    /**
     * Check if user can perform an action
     */
    public function can(Model $billable, string $action): bool
    {
        $plan = $billable->billing_plan ?? 'free';

        return match ($action) {
            'create_monitor' => $plan !== 'free',
            'use_slack' => in_array($plan, ['team', 'business'], true),
            'use_webhooks' => in_array($plan, ['team', 'business'], true),
            'invite_users' => in_array($plan, ['team', 'business'], true),
            'view_full_history' => in_array($plan, ['pro', 'team', 'business'], true),
            'sla_reports' => true, // Available to all
            'priority_support' => $plan === 'business',
            default => false,
        };
    }

    /**
     * Get current resource usage for a user
     */
    public function getUsage(User $user): array
    {
        $plan = $this->getPlan($user);
        $monitorCount = $user->currentTeam->monitors()->count();
        $userCount = $user->currentTeam->users()->count();
        
        // Get check count for current billing period
        $checkCount = $this->getCheckCountForPeriod($user);
        
        return [
            'monitors' => [
                'used' => $monitorCount,
                'limit' => $plan['monitors'],
                'percentage' => $plan['monitors'] > 0 ? round(($monitorCount / $plan['monitors']) * 100) : 0,
                'available' => max(0, $plan['monitors'] - $monitorCount),
                'at_limit' => $monitorCount >= $plan['monitors'],
            ],
            'users' => [
                'used' => $userCount,
                'limit' => $plan['users'],
                'percentage' => $plan['users'] > 0 ? round(($userCount / $plan['users']) * 100) : 0,
                'available' => max(0, $plan['users'] - $userCount),
                'at_limit' => $userCount >= $plan['users'],
            ],
            'checks_this_period' => [
                'count' => $checkCount,
                'interval' => $plan['check_interval'],
            ],
            'storage' => [
                'history_days' => $plan['history_days'] ?? 'unlimited',
            ],
        ];
    }

    /**
     * Get plan configuration
     */
    public function getPlan(User $user): array
    {
        $planName = $user->billing_plan ?? 'free';
        $plan = config("billing.plans.{$planName}", config('billing.plans.free'));
        
        // Apply any custom overrides
        if ($user->monitor_limit_override) {
            $plan['monitors'] = $user->monitor_limit_override;
        }
        if ($user->user_limit_override) {
            $plan['users'] = $user->user_limit_override;
        }
        
        return $plan;
    }

    /**
     * Check if user can upgrade to a plan
     */
    public function canUpgrade(User $user, string $plan): bool
    {
        $currentPlan = $user->billing_plan ?? 'free';
        $planHierarchy = ['free' => 0, 'pro' => 1, 'team' => 2, 'business' => 3];
        
        return ($planHierarchy[$plan] ?? 0) > ($planHierarchy[$currentPlan] ?? 0);
    }

    /**
     * Check if user can downgrade to a plan
     */
    public function canDowngrade(User $user, string $plan): bool
    {
        $currentPlan = $user->billing_plan ?? 'free';
        $planHierarchy = ['free' => 0, 'pro' => 1, 'team' => 2, 'business' => 3];
        
        return ($planHierarchy[$plan] ?? 0) < ($planHierarchy[$currentPlan] ?? 0);
    }

    /**
     * Calculate proration for plan change
     */
    public function calculateProration(User $user, string $newPlan): array
    {
        $currentPlan = config("billing.plans.{$user->billing_plan}");
        $targetPlan = config("billing.plans.{$newPlan}");
        
        if (!$user->next_bill_at) {
            return [
                'type' => 'new',
                'amount_due' => $targetPlan['price'],
                'proration' => 0,
                'total' => $targetPlan['price'],
            ];
        }
        
        $daysRemaining = now()->diffInDays($user->next_bill_at);
        $daysInPeriod = 30; // Assuming monthly billing
        
        $currentPlanDaily = $currentPlan['price'] / $daysInPeriod;
        $newPlanDaily = $targetPlan['price'] / $daysInPeriod;
        
        $unusedCredit = $currentPlanDaily * $daysRemaining;
        $newChargeForPeriod = $newPlanDaily * $daysRemaining;
        
        $proration = $newChargeForPeriod - $unusedCredit;
        
        return [
            'type' => $this->canUpgrade($user, $newPlan) ? 'upgrade' : 'downgrade',
            'days_remaining' => $daysRemaining,
            'unused_credit' => round($unusedCredit, 2),
            'new_charge' => round($newChargeForPeriod, 2),
            'proration' => round($proration, 2),
            'amount_due' => max(0, round($proration, 2)),
            'next_bill_at' => $user->next_bill_at,
            'new_monthly_price' => $targetPlan['price'],
        ];
    }

    /**
     * Get all invoices for a user
     */
    public function getInvoices(User $user): Collection
    {
        // This assumes you have a BillingInvoice model
        // If not, you'll need to fetch from Paddle API
        return BillingInvoice::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get a specific invoice
     */
    public function getInvoice(User $user, string $id)
    {
        return BillingInvoice::where('user_id', $user->id)
            ->where('id', $id)
            ->first();
    }

    /**
     * Get upcoming invoice preview
     */
    public function getUpcomingInvoice(User $user): ?array
    {
        if (!$user->next_bill_at || $user->billing_status !== 'active') {
            return null;
        }
        
        $plan = $this->getPlan($user);
        
        return [
            'date' => $user->next_bill_at,
            'amount' => $plan['price'],
            'items' => [
                [
                    'description' => "{$plan['name']} Plan",
                    'amount' => $plan['price'],
                ],
            ],
        ];
    }

    /**
     * Update payment method
     */
    public function updatePaymentMethod(User $user, string $paymentMethodId): bool
    {
        try {
            // Update via Paddle API
            // This is a placeholder - implement actual Paddle API call
            $user->has_payment_method = true;
            $user->save();
            
            Log::info('Payment method updated', ['user_id' => $user->id]);
            return true;
        } catch (\Exception $e) {
            Log::error('Payment method update failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get plan features
     */
    public function getPlanFeatures(string $plan): array
    {
        $planConfig = config("billing.plans.{$plan}");
        return $planConfig['features'] ?? [];
    }

    /**
     * Check if user is at resource limit
     */
    public function isAtLimit(User $user, string $resource): bool
    {
        $usage = $this->getUsage($user);
        
        return match ($resource) {
            'monitors' => $usage['monitors']['at_limit'],
            'users' => $usage['users']['at_limit'],
            default => false,
        };
    }

    /**
     * Check if user is in grace period
     */
    public function isInGracePeriod(User $user): bool
    {
        if ($user->billing_status !== 'past_due') {
            return false;
        }

        if (! $user->grace_ends_at) {
            return true;
        }

        return $user->grace_ends_at->isFuture();
    }

    /**
     * Check if user is on trial
     */
    public function isOnTrial(User $user): bool
    {
        if (!$user->trial_ends_at) {
            return false;
        }
        
        return $user->trial_ends_at->isFuture();
    }

    /**
     * Start trial period
     */
    public function startTrial(User $user, int $days = 14): bool
    {
        try {
            $user->trial_ends_at = now()->addDays($days);
            $user->save();
            
            Log::info('Trial started', ['user_id' => $user->id, 'days' => $days]);
            return true;
        } catch (\Exception $e) {
            Log::error('Trial start failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Enter grace period
     */
    public function enterGracePeriod(User $user, int $days = 7): bool
    {
        try {
            $user->billing_status = 'past_due';
            $user->grace_ends_at = now()->addDays($days);
            $user->save();
            
            Log::info('Past due period started', ['user_id' => $user->id]);
            return true;
        } catch (\Exception $e) {
            Log::error('Past due period failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get check count for current billing period
     */
    protected function getCheckCountForPeriod(User $user): int
    {
        $startDate = $user->last_bill_at ?? $user->created_at;
        $endDate = now();
        
        return Monitor::where('team_id', $user->current_team_id)
            ->withCount(['checks' => function ($query) use ($startDate, $endDate) {
                $query->whereBetween('checked_at', [$startDate, $endDate]);
            }])
            ->get()
            ->sum('checks_count');
    }

    /**
     * Get all available plans
     */
    public function getAllPlans(): array
    {
        return config('billing.plans');
    }

    /**
     * Get all available add-ons
     */
    public function getAllAddons(): array
    {
        return config('billing.addons');
    }

    /**
     * Check if add-on is available for user's plan
     */
    public function isAddonAvailable(User $user, string $addon): bool
    {
        $addonConfig = config("billing.addons.{$addon}");
        $userPlan = $user->billing_plan ?? 'free';
        
        return in_array($userPlan, $addonConfig['allowed_plans'] ?? []);
    }

    /**
     * Calculate Monthly Recurring Revenue
     */
    public function calculateMRR(): float
    {
        return Cache::remember('mrr', 3600, function () {
            return User::where('billing_status', 'active')
                ->whereNotNull('billing_plan')
                ->get()
                ->sum(function ($user) {
                    $plan = config("billing.plans.{$user->billing_plan}");
                    return $plan['price'] ?? 0;
                });
        });
    }

    /**
     * Calculate Annual Recurring Revenue
     */
    public function calculateARR(): float
    {
        return $this->calculateMRR() * 12;
    }

    /**
     * Get subscription statistics
     */
    public function getSubscriptionStats(): array
    {
        return Cache::remember('subscription_stats', 3600, function () {
            $total = User::count();
            $active = User::where('billing_status', 'active')->count();
            $free = User::where('billing_plan', 'free')->orWhereNull('billing_plan')->count();
            $pro = User::where('billing_plan', 'pro')->count();
            $team = User::where('billing_plan', 'team')->count();
            
            return [
                'total' => $total,
                'active_subscriptions' => $active,
                'free' => $free,
                'pro' => $pro,
                'team' => $team,
                'conversion_rate' => $total > 0 ? round(($active / $total) * 100, 2) : 0,
                'mrr' => $this->calculateMRR(),
                'arr' => $this->calculateARR(),
            ];
        });
    }

    /**
     * Get churn metrics
     */
    public function getChurnMetrics(): array
    {
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();
        
        $activeStart = User::where('billing_status', 'active')
            ->where('last_bill_at', '<', $startOfMonth)
            ->count();
            
        $churned = User::where('billing_status', 'cancelled')
            ->whereBetween('updated_at', [$startOfMonth, $endOfMonth])
            ->count();
        
        $churnRate = $activeStart > 0 ? round(($churned / $activeStart) * 100, 2) : 0;
        
        return [
            'churned_this_month' => $churned,
            'active_start_of_month' => $activeStart,
            'churn_rate' => $churnRate,
        ];
    }

    /**
     * Process failed payment
     */
    public function handleFailedPayment(User $user, array $data): void
    {
        Log::warning('Payment failed', [
            'user_id' => $user->id,
            'data' => $data,
        ]);
        
        // Enter grace period
        $this->enterGracePeriod($user);
        
        // Send notification email
        // Mail::to($user)->send(new PaymentFailedMail($user, $data));
    }

    /**
     * Process successful payment
     */
    public function handleSuccessfulPayment(User $user, array $data): void
    {
        Log::info('Payment successful', [
            'user_id' => $user->id,
            'amount' => $data['amount'] ?? null,
        ]);
        
        // Clear grace period
        $user->billing_status = 'active';
        $user->grace_ends_at = null;
        $user->last_bill_at = now();
        $user->save();
        
        // Create invoice record
        $this->createInvoice($user, $data);
    }

    /**
     * Create invoice record
     */
    protected function createInvoice(User $user, array $data): void
    {
        // This assumes you have a BillingInvoice model
        // Implement according to your needs
        /*
        BillingInvoice::create([
            'user_id' => $user->id,
            'paddle_invoice_id' => $data['invoice_id'] ?? null,
            'number' => $data['invoice_number'] ?? null,
            'amount' => $data['amount'] ?? 0,
            'currency' => $data['currency'] ?? 'USD',
            'status' => 'paid',
            'paid_at' => now(),
        ]);
        */
    }
}
