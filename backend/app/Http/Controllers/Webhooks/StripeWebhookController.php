<?php

namespace App\Http\Controllers\Webhooks;

use Illuminate\Http\Request;
use Stripe\Webhook;
use App\Models\Order;
use App\Events\PaymentConfirmed;

class StripeWebhookController
{
    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            $orderId = $session->metadata->order_id;

            $order = Order::findOrFail($orderId);

            $order->update(['status' => 'paid']);
            event(new PaymentConfirmed($order));
        }

        return response()->json(['status' => 'success']);
    }
}
