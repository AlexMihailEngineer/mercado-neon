<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Payment\StripeService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CheckoutController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Order $order, StripeService $stripeService)
    {
        // 1. State Validation: Prevent generating links for already paid orders
        if ($order->status === 'paid') {
            return back()->with('error', 'SYSTEM_ERR: Invoice already marked as PAID.');
        }

        // 2. Generate the Stripe Session via your Service
        // This method already updates the order with the stripe_session_id inside the service
        $sessionUrl = $stripeService->createCheckoutSession($order);

        // 3. The Critical External Redirect
        // Standard redirect()->away() will fail because Inertia expects a JSON response or an internal route.
        // Inertia::location() tells the Vue frontend to perform a hard `window.location = ...` redirect.
        return Inertia::location($sessionUrl);
    }
}
