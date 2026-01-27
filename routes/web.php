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

// ============================================================================
// PUBLIC STATUS PAGES (No authentication required)
// ============================================================================

// Individual monitor status by UUID
Route::get('/status/monitor/{uuid}', function($uuid) {
    return view('livewire.pages.public.status-enhanced', [
        'identifier' => $uuid,
        'type' => 'monitor'
    ]);
})->name('public.monitor.status');

// User's public status page
Route::get('/status/user/{userId}', function($userId) {
    return view('livewire.pages.public.status-enhanced', [
        'identifier' => $userId,
        'type' => 'user'
    ]);
})->name('public.user.status');

// Team public status page by slug
Route::get('/status/{slug}', function($slug) {
    return view('livewire.pages.public.status-enhanced', [
        'identifier' => $slug,
        'type' => 'team'
    ]);
})->name('public.team.status');

// Legacy public status (backward compatibility)
Volt::route('/status', 'pages.public.status')->name('public.status.legacy');

// ============================================================================
// AUTHENTICATED ROUTES
// ============================================================================

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Volt::route('/dashboard', 'pages.dashboard')->name('dashboard');

    Route::prefix('app')->group(function () {
        Volt::route('/monitors', 'pages.monitors.index')->name('monitors.index');
        Volt::route('/monitors/create', 'pages.monitors.create')->name('monitors.create');
        Volt::route('/monitors/{monitor}', 'pages.monitors.show')->name('monitors.show');
        Volt::route('/monitors/{monitor}/edit', 'pages.monitors.edit')->name('monitors.edit');

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

// ============================================================================
// BILLING ROUTES
// ============================================================================

Route::middleware(['auth', 'verified'])
    ->prefix('app')
    ->name('billing.')
    ->group(function () {
        Route::post('/billing/checkout', [BillingController::class, 'checkout'])->name('checkout');
        Volt::route('/billing/checkout', 'pages.billing.checkout')->name('checkout.page');
        Route::get('/billing/success', [BillingController::class, 'success'])->name('success');
        Route::post('/billing/cancel', [BillingController::class, 'cancel'])->name('cancel');
    });

// ============================================================================
// USER SETTINGS
// ============================================================================

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/timezone/update', [\App\Http\Controllers\TimezoneController::class, 'update'])->name('timezone.update');
});

// ============================================================================
// WEBHOOKS (Outside auth middleware, CSRF disabled)
// ============================================================================

Route::post('/webhooks/paddle', PaddleWebhookController::class)->name('webhooks.paddle');