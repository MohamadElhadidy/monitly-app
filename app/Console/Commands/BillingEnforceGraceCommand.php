<?php

namespace App\Console\Commands;

use App\Services\Billing\PlanEnforcer;
use Illuminate\Console\Command;

class BillingEnforceGraceCommand extends Command
{
    protected $signature = 'billing:enforce-grace';
    protected $description = 'Downgrade accounts whose grace period ended and enforce plan limits.';

    public function handle(PlanEnforcer $enforcer): int
    {
        $enforcer->enforceGraceDowngrades();

        $this->info('Grace enforcement completed.');

        return self::SUCCESS;
    }
}