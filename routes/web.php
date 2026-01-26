<?php

use App\Http\Controllers\Sla\DownloadMonitorSlaReportController;
use App\Http\Controllers\Webhooks\PaddleWebhookController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use App\Http\Controllers\System\HealthController;
use App\Http\Controllers\Billing\BillingController;


require(__DIR__.'/emails.php');

Route::get('/_health', HealthController::class)
->middleware(['throttle:60,1'])
->name('system.health');

// Public status pages
Volt::route('/status', 'pages.public.status')->name('public.status');
Volt::route('/status/{slug}', 'pages.public.team-status')->name('public.team-status');


Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware(['auth', 'verified'])->group(function () {
Volt::route('/dashboard', 'pages.dashboard')->name('dashboard');

    Route::prefix('app')->group(function () {
        Volt::route('/monitors', 'pages.monitors.index')->name('monitors.index');
        Volt::route('/monitors/{monitor}', 'pages.monitors.show')->name('monitors.show');

        Volt::route('/sla', 'pages.sla.overview')->name('sla.overview');

        Volt::route('/billing', 'pages.billing.index')->name('billing.index');

        Volt::route('/teams/{team}/notifications', 'pages.team.notifications')->name('team.notifications');

        Route::get('/monitors/{monitor}/sla-reports/{report}/download', DownloadMonitorSlaReportController::class)
            ->middleware('signed')
            ->name('sla.reports.download');
            
    });
    
    
   



    // Admin (internal)
    Route::prefix('admin')->middleware('can:access-admin')->group(function () {
        Volt::route('/', 'pages.admin.index')->name('admin.index');
        Volt::route('/users', 'pages.admin.users')->name('admin.users');
        Volt::route('/teams', 'pages.admin.teams')->name('admin.teams');
        Volt::route('/monitors', 'pages.admin.monitors')->name('admin.monitors');
        Volt::route('/subscriptions', 'pages.admin.subscriptions')->name('admin.subscriptions');
        Volt::route('/audit-logs', 'pages.admin.audit-logs')->name('admin.audit_logs');
        Volt::route('/system', 'pages.admin.system')->name('admin.system');
    });
});




Route::middleware(['auth', 'verified'])
    ->prefix('app')
    ->name('billing.')
    ->group(function () {
       Volt::route('/billing', 'pages.billing.index')
                ->name('index');
        Route::post('/billing/checkout', [\App\Http\Controllers\Billing\BillingController::class, 'checkout'])->name('checkout');
        Route::get('/billing/success', [\App\Http\Controllers\Billing\BillingController::class, 'success'])->name('success');
        Route::post('/billing/cancel', [\App\Http\Controllers\Billing\BillingController::class, 'cancel'])->name('cancel');
    });