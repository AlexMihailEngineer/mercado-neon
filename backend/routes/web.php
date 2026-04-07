<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Payment\CheckoutController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// The main B2B Merchant Dashboard
Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Protected Merchant Actions
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Merchant generates the Stripe Checkout Session
    Route::post('/checkout/{order}', CheckoutController::class)->name('checkout.initiate');
});

// Public Client Facing Routes (Where Stripe sends the buyer after payment)
Route::get('/payment/success', function () {
    // will create this minimal Vue component later
    return Inertia::render('Checkout/Success');
})->name('payment.success');

Route::get('/payment/cancel', function () {
    return Inertia::render('Checkout/Cancel');
})->name('payment.cancel');

require __DIR__ . '/auth.php';
