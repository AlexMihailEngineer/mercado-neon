<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Payment\StripeService;
use Illuminate\Http\RedirectResponse;

class CheckoutController extends Controller
{
    public function __construct(
        protected StripeService $stripeService
    ) {}

    public function __invoke(Order $order): RedirectResponse
    {
        try {
            $checkoutUrl = $this->stripeService->createCheckoutSession($order);
            return redirect()->away($checkoutUrl);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Could not initiate payment.']);
        }
    }
}
