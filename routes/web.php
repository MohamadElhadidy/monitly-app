<?php

use App\Http\Controllers\Sla\DownloadMonitorSlaReportController;
use App\Http\Controllers\Billing\BillingController;
use App\Http\Controllers\Billing\CheckoutController;
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
// BILLING ROUTES - Laravel Cashier (Paddle) Integration
// Following: https://laravel.com/docs/12.x/cashier-paddle
// ============================================================================

Route::middleware(['auth', 'verified'])->prefix('billing')->name('billing.')->group(function () {
    
    // Main billing dashboard

    Volt::route('/', 'pages.billing.index')->name('index');

    Route::get('/checkout', [CheckoutController::class, 'checkout'])->name('checkout');
    Route::get('/checkout/success', [CheckoutController::class, 'success'])->name('success');
    Route::get('/checkout/cancel', [CheckoutController::class, 'cancel'])->name('cancel');
    
    // Paddle Customer Portal (for managing subscriptions, payment methods, invoices)
    Route::get('/portal', [BillingController::class, 'portal'])->name('portal');
    
    // Subscription actions
    Route::post('/cancel', [BillingController::class, 'cancel'])->name('cancel');
    Route::post('/cancel-now', [BillingController::class, 'cancelNow'])->name('cancel.now');
    Route::post('/resume', [BillingController::class, 'resume'])->name('resume');
    Route::post('/swap', [BillingController::class, 'swap'])->name('swap');
    Route::post('/pause', [BillingController::class, 'pause'])->name('pause');
    Route::post('/unpause', [BillingController::class, 'unpause'])->name('unpause');
    Route::post('/quantity', [BillingController::class, 'updateQuantity'])->name('quantity');
    
    // Invoices
    Route::get('/invoices/{transaction}/download', [BillingController::class, 'downloadInvoice'])->name('invoices.download');
});

// ============================================================================
// PADDLE WEBHOOK
// This route is automatically registered by Cashier at /paddle/webhook
// But we can also manually define it if needed
// ============================================================================
// Route::post('/paddle/webhook', '\Laravel\Paddle\Http\Controllers\WebhookController');

// ============================================================================
// USER SETTINGS
// ============================================================================

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/timezone/update', [\App\Http\Controllers\TimezoneController::class, 'update'])->name('timezone.update');
});