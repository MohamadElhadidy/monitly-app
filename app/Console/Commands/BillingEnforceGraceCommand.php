<?php

namespace App\Console\Commands;

use App\Services\Billing\PlanEnforcer;
use Illuminate\Console\Command;

class BillingEnforceGraceCommand extends Command
{
    protected $signature = 'billing:enforce-grace';
    protected $description = 'Enforce plan limits and apply billing locks.';

    public function handle(PlanEnforcer $enforcer): int
    {
        $enforcer->enforceGraceDowngrades();

        $this->info('Plan limits enforced.');

        return self::SUCCESS;
    }
}
