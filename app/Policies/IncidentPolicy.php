<?php

namespace App\Policies;

use App\Models\Incident;
use App\Models\User;

class IncidentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Incidents follow monitor visibility.
     */
    public function view(User $user, Incident $incident): bool
    {
        $monitor = $incident->monitor;
        if (! $monitor) {
            return false;
        }

        return app(MonitorPolicy::class)->view($user, $monitor);
    }

    /**
     * Mutations will be done by system jobs; admins can later override.
     * Skeleton: deny by default.
     */
    public function update(User $user, Incident $incident): bool
    {
        return false;
    }

    public function delete(User $user, Incident $incident): bool
    {
        return false;
    }
}
