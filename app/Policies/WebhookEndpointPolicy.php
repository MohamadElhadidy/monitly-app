<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WebhookEndpoint;

class WebhookEndpointPolicy
{
    public function view(User $user, WebhookEndpoint $endpoint): bool
    {
        $team = $endpoint->team;
        return $team ? $user->belongsToTeam($team) : false;
    }

    public function create(User $user, $team): bool
    {
        return $user->ownsTeam($team) || $user->hasTeamRole($team, 'admin');
    }

    public function update(User $user, WebhookEndpoint $endpoint): bool
    {
        $team = $endpoint->team;
        return $team ? ($user->ownsTeam($team) || $user->hasTeamRole($team, 'admin')) : false;
    }

    public function delete(User $user, WebhookEndpoint $endpoint): bool
    {
        return $this->update($user, $endpoint);
    }
}
