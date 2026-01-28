<?php

namespace App\Policies;

use App\Models\Monitor;
use App\Models\MonitorMemberPermission;
use App\Models\Team;
use App\Models\User;

class MonitorPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * View a monitor:
     * - Individual: owner only
     * - Team:
     *   - Owner/Admin: any monitor in team
     *   - Member: only monitors with ANY per-monitor permission granted
     */
    public function view(User $user, Monitor $monitor): bool
    {
        if (! $monitor->isTeamMonitor()) {
            return (int) $monitor->user_id === (int) $user->id;
        }

        $team = $monitor->team;
        if (! $team || ! $user->belongsToTeam($team)) {
            return false;
        }

        if ($this->isOwnerOrAdmin($user, $team)) {
            return true;
        }

        $perm = MonitorMemberPermission::query()
            ->where('monitor_id', $monitor->id)
            ->where('user_id', $user->id)
            ->first();

        return $perm?->hasAnyPermission() ?? false;
    }

    /**
     * Create a monitor.
     * - Individual (team null): allowed
     * - Team: owner/admin only
     */
    public function create(User $user, ?Team $team = null): bool
    {
        if (is_null($team)) {
            return true;
        }

        return $this->isOwnerOrAdmin($user, $team);
    }

    /**
     * Manage monitor settings (name/url/public/etc).
     * - Individual: owner only
     * - Team: owner/admin true; member requires edit_settings
     */
    public function editSettings(User $user, Monitor $monitor): bool
    {
        return $this->checkMemberPermission($user, $monitor, 'edit_settings');
    }

    /**
     * Pause/resume checks.
     * - Individual: owner only
     * - Team: owner/admin true; member requires pause_resume
     */
    public function pauseResume(User $user, Monitor $monitor): bool
    {
        return $this->checkMemberPermission($user, $monitor, 'pause_resume');
    }

    /**
     * View logs (checks timeline).
     * - Individual: owner only
     * - Team: owner/admin true; member requires view_logs
     */
    public function viewLogs(User $user, Monitor $monitor): bool
    {
        return $this->checkMemberPermission($user, $monitor, 'view_logs');
    }

    /**
     * Receive alerts (used by notifier later).
     * - Individual: owner only
     * - Team: owner/admin true; member requires receive_alerts
     */
    public function receiveAlerts(User $user, Monitor $monitor): bool
    {
        return $this->checkMemberPermission($user, $monitor, 'receive_alerts');
    }

    /**
     * Owner/Admin can manage per-monitor permissions UI.
     */
    public function managePermissions(User $user, Monitor $monitor): bool
    {
        if (! $monitor->isTeamMonitor()) {
            return false;
        }

        $team = $monitor->team;
        if (! $team || ! $user->belongsToTeam($team)) {
            return false;
        }

        return $this->isOwnerOrAdmin($user, $team);
    }

    /**
     * Update a monitor = edit settings (for now).
     */
    public function update(User $user, Monitor $monitor): bool
    {
        return $this->editSettings($user, $monitor);
    }

    /**
     * Delete a monitor:
     * - Individual: owner only
     * - Team: owner/admin only
     */
    public function delete(User $user, Monitor $monitor): bool
    {
        if (! $monitor->isTeamMonitor()) {
            return (int) $monitor->user_id === (int) $user->id;
        }

        $team = $monitor->team;
        return $team ? $this->isOwnerOrAdmin($user, $team) : false;
    }

    private function isOwnerOrAdmin(User $user, Team $team): bool
    {
        return $user->ownsTeam($team) || $user->hasTeamRole($team, 'admin');
    }

    private function checkMemberPermission(User $user, Monitor $monitor, string $field): bool
    {
        // Individual monitors: owner has all permissions
        if (! $monitor->isTeamMonitor()) {
            return (int) $monitor->user_id === (int) $user->id;
        }

        $team = $monitor->team;
        if (! $team || ! $user->belongsToTeam($team)) {
            return false;
        }

        if ($this->isOwnerOrAdmin($user, $team)) {
            return true;
        }

        // Member: must have ANY access to the monitor, and the specific field for the action
        $perm = MonitorMemberPermission::query()
            ->where('monitor_id', $monitor->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $perm || ! $perm->hasAnyPermission()) {
            return false;
        }

        return (bool) ($perm->{$field} ?? false);
    }
}
