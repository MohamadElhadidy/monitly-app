<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use App\Models\Monitor;
use App\Observers\MonitorObserver;
use App\Models\MonitorMemberPermission;
use App\Observers\MonitorMemberPermissionObserver;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Event;
use Laravel\Paddle\Events\WebhookHandled;
use App\Listeners\CapturePaddleWebhook;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->environment('production')) {
            URL::forceScheme('https');
            }
            
            
            if (Schema::hasTable('billing_webhook_events')) {
                        Event::listen(
                        WebhookHandled::class,
                        CapturePaddleWebhook::class
            );
            }

        $this->configureDefaults();
        Monitor::observe(MonitorObserver::class);
        
        // Permission audit logs (only if Part 3 table exists)
        try {
            if (Schema::hasTable('monitor_member_permissions')) {
                 MonitorMemberPermission::observe(MonitorMemberPermissionObserver::class);
        }
        } catch (\Throwable $e) {
        // ignore during early install
        }

    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }
}
