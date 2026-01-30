<?php

namespace App\Services\Admin;

use App\Services\Audit\Audit;
use Illuminate\Database\Eloquent\Model;

class AdminBillingService
{
    public function requestResync(Model $billable, string $reason): void
    {
        Audit::log('admin.billing.resync_requested', $billable, $billable->getAttribute('team_id'), [
            'reason' => $reason,
            'billable_type' => $billable::class,
            'billable_id' => $billable->getKey(),
        ]);
    }
}
