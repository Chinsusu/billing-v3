<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\InvoiceController as AdminInvoiceController;
use App\Http\Controllers\Admin\PaymentEventController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\BankWebhookSandboxController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ProductCatalogController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\WalletTopUpController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/products'));
Route::get('/products', ProductCatalogController::class)->name('products.index');
Route::post('/webhooks/bank/sandbox', BankWebhookSandboxController::class)->name('webhooks.bank-sandbox');

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
    });
});
