<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\BankIntegrationController;
use App\Http\Controllers\Admin\BankIntegrationTestController;
use App\Http\Controllers\Admin\InvoiceController as AdminInvoiceController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\PaymentEventController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProviderActionJobController;
use App\Http\Controllers\Admin\ProviderActionJobRetryController;
use App\Http\Controllers\Admin\ProvisioningJobController;
use App\Http\Controllers\Admin\ProvisioningJobRetryController;
use App\Http\Controllers\Admin\ProvisioningProviderAccountController;
use App\Http\Controllers\Admin\ProvisioningProviderAccountTestController;
use App\Http\Controllers\Admin\ServiceController as AdminServiceController;
use App\Http\Controllers\Admin\ServiceProviderCancelController;
use App\Http\Controllers\Admin\ServiceProviderSyncController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\BankWebhookSandboxController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Internal\ProvisioningJobExecutionController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductCatalogController;
use App\Http\Controllers\ProductOrderController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\ServiceRenewalController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\WalletTopUpController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/products'));
Route::get('/products', ProductCatalogController::class)->name('products.index');
Route::post('/webhooks/bank/sandbox', BankWebhookSandboxController::class)->name('webhooks.bank-sandbox');
Route::post('/internal/provisioning/jobs/{job}/execute', ProvisioningJobExecutionController::class)
    ->withoutMiddleware([ValidateCsrfToken::class])
    ->name('internal.provisioning-jobs.execute');

Route::middleware('guest')->group(function (): void {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/wallet', WalletController::class)->name('wallet.show');
    Route::post('/wallet/top-ups', [WalletTopUpController::class, 'store'])->name('wallet.top-ups.store');
    Route::get('/wallet/top-ups/{paymentIntent}', [WalletTopUpController::class, 'show'])->name('wallet.top-ups.show');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::post('/invoices/{invoice}/pay', [InvoiceController::class, 'pay'])->name('invoices.pay');
    Route::post('/products/{product}/order', [ProductOrderController::class, 'store'])->name('products.order');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
    Route::get('/services/{service}', [ServiceController::class, 'show'])->name('services.show');
    Route::post('/services/{service}/renew', ServiceRenewalController::class)->name('services.renew');

    Route::middleware('permission:admin.access')->prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/', AdminDashboardController::class)->name('dashboard');
        Route::get('/products', [ProductController::class, 'index'])->middleware('permission:products.view')->name('products.index');
        Route::get('/products/create', [ProductController::class, 'create'])->middleware('permission:products.create')->name('products.create');
        Route::post('/products', [ProductController::class, 'store'])->middleware('permission:products.create')->name('products.store');
        Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->middleware('permission:products.update')->name('products.edit');
        Route::put('/products/{product}', [ProductController::class, 'update'])->middleware('permission:products.update')->name('products.update');
        Route::delete('/products/{product}', [ProductController::class, 'destroy'])->middleware('permission:products.delete')->name('products.destroy');
        Route::get('/invoices', [AdminInvoiceController::class, 'index'])->middleware('permission:invoices.view')->name('invoices.index');
        Route::post('/invoices', [AdminInvoiceController::class, 'store'])->middleware('permission:invoices.create')->name('invoices.store');
        Route::get('/payment-events', PaymentEventController::class)->middleware('permission:payment_events.view')->name('payment-events.index');
        Route::get('/bank-integrations', [BankIntegrationController::class, 'index'])->middleware('permission:bank_integrations.manage')->name('bank-integrations.index');
        Route::get('/bank-integrations/create', [BankIntegrationController::class, 'create'])->middleware('permission:bank_integrations.manage')->name('bank-integrations.create');
        Route::post('/bank-integrations', [BankIntegrationController::class, 'store'])->middleware('permission:bank_integrations.manage')->name('bank-integrations.store');
        Route::get('/bank-integrations/{bankIntegration}/edit', [BankIntegrationController::class, 'edit'])->middleware('permission:bank_integrations.manage')->name('bank-integrations.edit');
        Route::put('/bank-integrations/{bankIntegration}', [BankIntegrationController::class, 'update'])->middleware('permission:bank_integrations.manage')->name('bank-integrations.update');
        Route::post('/bank-integrations/{bankIntegration}/test', BankIntegrationTestController::class)->middleware('permission:bank_integrations.manage')->name('bank-integrations.test');
        Route::get('/provisioning-provider-accounts', [ProvisioningProviderAccountController::class, 'index'])->middleware('permission:provisioning_provider_accounts.manage')->name('provisioning-provider-accounts.index');
        Route::get('/provisioning-provider-accounts/create', [ProvisioningProviderAccountController::class, 'create'])->middleware('permission:provisioning_provider_accounts.manage')->name('provisioning-provider-accounts.create');
        Route::post('/provisioning-provider-accounts', [ProvisioningProviderAccountController::class, 'store'])->middleware('permission:provisioning_provider_accounts.manage')->name('provisioning-provider-accounts.store');
        Route::get('/provisioning-provider-accounts/{provisioningProviderAccount}/edit', [ProvisioningProviderAccountController::class, 'edit'])->middleware('permission:provisioning_provider_accounts.manage')->name('provisioning-provider-accounts.edit');
        Route::put('/provisioning-provider-accounts/{provisioningProviderAccount}', [ProvisioningProviderAccountController::class, 'update'])->middleware('permission:provisioning_provider_accounts.manage')->name('provisioning-provider-accounts.update');
        Route::post('/provisioning-provider-accounts/{provisioningProviderAccount}/test', ProvisioningProviderAccountTestController::class)->middleware('permission:provisioning_provider_accounts.manage')->name('provisioning-provider-accounts.test');
        Route::get('/orders', [AdminOrderController::class, 'index'])->middleware('permission:orders.view')->name('orders.index');
        Route::get('/services', [AdminServiceController::class, 'index'])->middleware('permission:services.view')->name('services.index');
        Route::post('/services/{service}/sync-provider', ServiceProviderSyncController::class)->middleware('permission:services.view')->name('services.sync-provider');
        Route::post('/services/{service}/cancel-provider', ServiceProviderCancelController::class)->middleware('permission:services.view')->name('services.cancel-provider');
        Route::get('/provider-action-jobs', [ProviderActionJobController::class, 'index'])->middleware('permission:provisioning_jobs.view')->name('provider-action-jobs.index');
        Route::post('/provider-action-jobs/{providerActionJob}/retry', ProviderActionJobRetryController::class)->middleware('permission:provisioning_jobs.view')->name('provider-action-jobs.retry');
        Route::get('/provisioning-jobs', [ProvisioningJobController::class, 'index'])->middleware('permission:provisioning_jobs.view')->name('provisioning-jobs.index');
        Route::get('/provisioning-jobs/{provisioningJob}', [ProvisioningJobController::class, 'show'])->middleware('permission:provisioning_jobs.view')->name('provisioning-jobs.show');
        Route::post('/provisioning-jobs/{provisioningJob}/retry', ProvisioningJobRetryController::class)->middleware('permission:provisioning_jobs.view')->name('provisioning-jobs.retry');
    });
});
