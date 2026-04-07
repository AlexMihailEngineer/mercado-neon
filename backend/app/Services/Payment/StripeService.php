<?php

namespace App\Services\Payment;

use Stripe\StripeClient;
use App\Models\Order;

class StripeService
{
    protected StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    /**
     * Create a Stripe Checkout Session with EUR conversion.
     */
    public function createCheckoutSession(Order $order): string
    {
        $ronNormalized = number_format((float) $order->total_amount_ron, 2, '.', '');
        $ronCents = (int) str_replace('.', '', $ronNormalized);
        $amountInCents = intdiv($ronCents, 5);
        $remainder = $ronCents % 5;

        if ($remainder >= 3) {
            $amountInCents++;
        }

        $session = $this->stripe->checkout->sessions->create([
            'line_items' => [[
                'price_data' => [
                    'currency' => 'eur',
                    'unit_amount' => $amountInCents,
                    'product_data' => [
                        'name' => "MERCADO-NEON Order #{$order->invoice_number}",
                    ],
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'metadata' => [
                'order_id' => $order->id,
            ],
            'success_url' => route('payment.success'),
            'cancel_url' => route('payment.cancel'),
        ]);

        // Save the session ID to the order for tracking
        $order->update(['stripe_session_id' => $session->id]);

        return $session->url;
    }
}
