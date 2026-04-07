<?php

namespace App\Services\Payment;

use Stripe\StripeClient;
use App\Models\Order;
use Exception;

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
        // Stripe expects cents. Use the model's EUR attribute.
        $amountInCents = (int) round($order->total_amount_eur * 100);

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

    /**
     * Convert RON to EUR based on a defined exchange rate.
     */
    private function convertToEur(float $ronAmount): float
    {
        // Example rate: 1 EUR = 5.00 RON
        $exchangeRate = 5.00;
        return round($ronAmount / $exchangeRate, 2);
    }
}
