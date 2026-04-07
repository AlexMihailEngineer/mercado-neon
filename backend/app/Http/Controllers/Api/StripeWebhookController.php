<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Events\PaymentConfirmed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $endpointSecret = config('services.stripe.webhook_secret');

        try {
            // Verify the signature to ensure this actually came from Stripe
            $event = Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
        } catch (\Exception $e) {
            Log::error('STRIPE_WEBHOOK_SIG_FAIL: ' . $e->getMessage());
            return response()->json(['error' => 'Invalid Signature'], 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            $orderId = $session->metadata->order_id ?? null;

            if ($orderId) {
                $order = Order::find($orderId);

                if ($order && $order->status !== 'paid') {
                    $order->update(['status' => 'paid']);

                    // Trigger the Day 5 & 6 automation chain
                    event(new PaymentConfirmed($order));

                    Log::info("ORDER_PAID: Invoice {$order->invoice_number} settled.");
                }
            }
        }

        return response()->json(['status' => 'processed'], 200);
    }
}
