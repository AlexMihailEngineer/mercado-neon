<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Payment\CheckoutController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrderController;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// The main B2B Merchant Dashboard
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Protected Merchant Actions
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');

    // Merchant generates the Stripe Checkout Session
    Route::post('/checkout/{order}', CheckoutController::class)->name('checkout.initiate');
});

// Public Client Facing Routes (Where Stripe sends the buyer after payment)
Route::get('/payment/success', function (Request $request) {
    $sessionId = $request->query('session_id');

    if (!$sessionId) {
        if (Auth::check()) {
            return redirect()->route('dashboard')->with('error', 'No session ID provided.');
        }

        return redirect('/')->with('error', 'No session ID provided.');
    }

    $order = Order::where('stripe_session_id', $sessionId)->first();

    // If order is not found, redirect to dashboard with a warning
    if (!$order) {
        if (Auth::check()) {
            return redirect()->route('dashboard')->with('error', 'Transaction record not found.');
        }

        return redirect('/')->with('error', 'Transaction record not found.');
    }

    return Inertia::render('Checkout/Success', [
        'order' => $order
    ]);
})->name('payment.success');

Route::get('/payment/cancel', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard')->with('error', 'Payment cancelled.');
    }

    return redirect('/')->with('error', 'Payment cancelled.');
})->name('payment.cancel');

require __DIR__ . '/auth.php';
