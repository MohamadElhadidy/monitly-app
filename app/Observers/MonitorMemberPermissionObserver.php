<?php

namespace App\Observers;

use App\Models\MonitorMemberPermission;
use App\Services\Audit\Audit;

class MonitorMemberPermissionObserver
{
    public function created(MonitorMemberPermission $perm): void
    {
        Audit::log(
            action: 'permission.monitor_grant.created',
            subject: $perm,
            teamId: $perm->team_id ?? null,
            meta: $this->permMeta($perm)
        );
    }

    public function updated(MonitorMemberPermission $perm): void
    {
        Audit::log(
            action: 'permission.monitor_grant.updated',
            subject: $perm,
            teamId: $perm->team_id ?? null,
            meta: $this->permMeta($perm)
        );
    }

    public function deleted(MonitorMemberPermission $perm): void
    {
        Audit::log(
            action: 'permission.monitor_grant.deleted',
            subject: $perm,
            teamId: $perm->team_id ?? null,
            meta: $this->permMeta($perm)
        );
    }

    private function permMeta(MonitorMemberPermission $perm): array
    {
        return [
            'monitor_id' => $perm->monitor_id ?? null,
            'user_id' => $perm->user_id ?? null,
            'view_logs' => (bool) ($perm->view_logs ?? false),
            'receive_alerts' => (bool) ($perm->receive_alerts ?? false),
            'pause_resume' => (bool) ($perm->pause_resume ?? false),
            'edit_settings' => (bool) ($perm->edit_settings ?? false),
        ];
    }
}