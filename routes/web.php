<?php

use App\Http\Controllers\Sla\DownloadMonitorSlaReportController;
use App\Http\Controllers\Billing\BillingController;
use App\Http\Controllers\Billing\CheckoutController;
use App\Http\Controllers\Billing\PaddleWebhookController;
use App\Http\Controllers\Webhooks\PaddleWebhookController as CashierPaddleWebhookController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use App\Http\Controllers\System\HealthController;

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
        Volt::route('/incidents', 'pages.incidents.index')->name('incidents.index');
        Volt::route('/integrations', 'pages.integrations.index')->name('integrations.index');
        Volt::route('/docs', 'pages.docs.index')->name('docs.index');
        Volt::route('/teams/{team}/notifications', 'pages.team.notifications')->name('team.notifications');

        Route::get('/monitors/{monitor}/sla-reports/{report}/download', DownloadMonitorSlaReportController::class)
            ->middleware('signed')
            ->name('sla.reports.download');

        // Billing routes
        Route::prefix('billing')->name('billing.')->group(function () {
            Volt::route('/', 'pages.billing.index')->name('index');
            Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout');
            Route::post('/checkout/change', [CheckoutController::class, 'applyChange'])->name('checkout.change');
            Volt::route('/success', 'pages.billing.success')->name('success');
            Volt::route('/cancel', 'pages.billing.cancel')->name('cancel');
            Volt::route('/history', 'pages.billing.history')->name('history');

            Route::get('/portal', [BillingController::class, 'portal'])->name('portal');
            Route::post('/cancel', [BillingController::class, 'cancel'])->name('cancel.plan');
            Route::get('/invoices/{transaction}/download', [BillingController::class, 'downloadInvoice'])->name('invoices.download');
        });
    });

    // Admin (owner-only)
    Route::prefix('admin')->middleware('owner-only')->group(function () {
        Volt::route('/', 'pages.admin.index')->name('admin.index');
        Volt::route('/revenue', 'pages.admin.revenue')->name('admin.revenue');
        Volt::route('/subscriptions', 'pages.admin.subscriptions')->name('admin.subscriptions');
        Volt::route('/payments', 'pages.admin.payments')->name('admin.payments');
        Volt::route('/refunds', 'pages.admin.refunds')->name('admin.refunds');
        Volt::route('/users', 'pages.admin.users')->name('admin.users');
        Volt::route('/teams', 'pages.admin.teams')->name('admin.teams');
        Volt::route('/usage', 'pages.admin.usage')->name('admin.usage');
        Volt::route('/queues', 'pages.admin.queues')->name('admin.queues');
        Volt::route('/jobs/failed', 'pages.admin.jobs-failed')->name('admin.jobs.failed');
        Volt::route('/webhooks/paddle', 'pages.admin.webhooks')->name('admin.webhooks');
        Volt::route('/errors', 'pages.admin.errors')->name('admin.errors');
        Volt::route('/notifications', 'pages.admin.notifications')->name('admin.notifications');
        Volt::route('/incidents', 'pages.admin.incidents')->name('admin.incidents');
        Volt::route('/audit', 'pages.admin.audit')->name('admin.audit');
        Volt::route('/settings', 'pages.admin.settings')->name('admin.settings');
    });
});

// ============================================================================
// PADDLE WEBHOOK
// Cashier Paddle webhook (official). Paddle should be pointed here.
// We still accept /webhooks/paddle for legacy/manual ingestion.
// ============================================================================
Route::post('/paddle/webhook', CashierPaddleWebhookController::class);
Route::post('/webhooks/paddle', [PaddleWebhookController::class, 'handle']);
