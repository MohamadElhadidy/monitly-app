<?php

use App\Http\Controllers\Sla\DownloadMonitorSlaReportController;
use App\Http\Controllers\Webhooks\PaddleWebhookController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use App\Http\Controllers\System\HealthController;
use App\Http\Controllers\Billing\BillingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;


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
// BILLING ROUTES - With Paddle Customer Portal Integration
// ============================================================================


Route::middleware(['auth', 'verified'])->prefix('billing')->name('billing.')->group(function () {
    // Main billing dashboard
    Volt::route('/', 'pages.billing.index')->name('index');
    Volt::route('/checkout.index', 'pages.billing.index')->name('checkout.index'); // Alias for backward compatibility
    
    // Plan selection and checkout
    Volt::route('/plans', 'pages.billing.plans')->name('plans');
    Route::post('/checkout', [BillingController::class, 'checkout'])->name('checkout');
    Volt::route('/checkout', 'pages.billing.checkout')->name('checkout.page');
    Volt::route('/success', 'pages.billing.success')->name('success');
    
    // Invoice management
    Volt::route('/invoices', 'pages.billing.invoices')->name('invoices');
    Route::get('/invoices/{id}/download', [BillingController::class, 'downloadInvoice'])->name('invoice.download');
    
    // Subscription management - Redirects to Paddle Customer Portal
    // This is the main route for managing subscriptions, payment methods, and billing preferences
    Route::get('/manage', [BillingController::class, 'manageSubscription'])->name('manage');
    
    // Legacy routes - now redirect to customer portal for seamless experience
    Route::post('/cancel', [BillingController::class, 'cancel'])->name('cancel');
    Route::post('/payment-method/update', [BillingController::class, 'updatePaymentMethod'])->name('payment-method.update');
    
});




Route::middleware('auth')->get('/billing/portal', function (Request $request) {
    $user = $request->user();

    // Cashier customer creation
    $customer = $user->customer ?? $user->createAsCustomer();

    $baseUrl = config('billing.sandbox')
        ? 'https://sandbox-api.paddle.com'
        : 'https://api.paddle.com'; // Paddle base URLs :contentReference[oaicite:0]{index=0}

    $endpoint = "{$baseUrl}/customers/{$customer->paddle_id}/portal-sessions"; // portal session endpoint :contentReference[oaicite:1]{index=1}
$res = Http::withToken(config('cashier.api_key'))
    ->acceptJson()
    ->withHeaders(['Paddle-Version' => '1'])
    ->send('POST', $endpoint);   // <-- no body
    // no body required for homepage link :contentReference[oaicite:3]{index=3}

    Log::error('Paddle portal session response', [
        'endpoint' => $endpoint,
        'status'   => $res->status(),
        'body'     => $res->json(),
    ]);

    if (! $res->successful()) {
        // Show details quickly while debugging
        return response()->json([
            'message' => 'Paddle portal session failed',
            'status'  => $res->status(),
            'body'    => $res->json(),
        ], 502);
    }

    $portalUrl = data_get($res->json(), 'data.urls.general.overview'); // returned link :contentReference[oaicite:4]{index=4}
    abort_unless($portalUrl, 502, 'Missing portal URL in Paddle response.');

    return redirect()->away($portalUrl);
})->name('billing.portal');

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




