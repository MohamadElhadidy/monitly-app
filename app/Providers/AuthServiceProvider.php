<?php

namespace App\Providers;

use App\Models\Incident;
use App\Models\Monitor;
use App\Models\NotificationChannel;
use App\Models\Team;
use App\Models\WebhookEndpoint;
use App\Policies\IncidentPolicy;
use App\Policies\MonitorPolicy;
use App\Policies\NotificationChannelPolicy;
use App\Policies\WebhookEndpointPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\User;


class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Monitor::class => MonitorPolicy::class,
        Incident::class => IncidentPolicy::class,
        NotificationChannel::class => NotificationChannelPolicy::class,
        WebhookEndpoint::class => WebhookEndpointPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        // Owner-only gates (billing + members management)
        Gate::define('team.manageBilling', function ($user, Team $team) {
            return $user->ownsTeam($team);
        });

        Gate::define('team.manageMembers', function ($user, Team $team) {
            return $user->ownsTeam($team);
        });
        
        Gate::define('access-admin', function (User $user): bool {
                return (bool) $user->is_admin;
        });

    }
}
