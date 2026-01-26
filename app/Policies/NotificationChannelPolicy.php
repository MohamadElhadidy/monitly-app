<?php

namespace App\Policies;

use App\Models\NotificationChannel;
use App\Models\User;

class NotificationChannelPolicy
{
    /**
     * Only owner/admin can manage team notification settings.
     */
    public function update(User $user, NotificationChannel $channel): bool
    {
        $team = $channel->team;
        if (! $team) {
            return false;
        }

        return $user->ownsTeam($team) || $user->hasTeamRole($team, 'admin');
    }
}
