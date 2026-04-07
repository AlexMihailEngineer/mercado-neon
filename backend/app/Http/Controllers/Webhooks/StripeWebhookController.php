<?php

namespace App\Http\Controllers\Webhooks;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
            $orderId = $session->metadata->order_id ?? null;

            if (! is_numeric($orderId)) {
                Log::warning('Stripe webhook checkout.session.completed missing/invalid order_id', [
                    'stripe_event_id' => $event->id ?? null,
                    'stripe_session_id' => $session->id ?? null,
                ]);
                return response()->json(['status' => 'success']);
            }

            $shouldDispatch = false;

            $order = DB::transaction(function () use ($orderId, $session, &$shouldDispatch) {
                $order = Order::query()->whereKey((int) $orderId)->lockForUpdate()->first();

                if (! $order) {
                    return null;
                }

                if ($order->stripe_session_id && $order->stripe_session_id !== $session->id) {
                    Log::warning('Stripe webhook checkout.session.completed session mismatch for order', [
                        'order_id' => $order->id,
                        'order_stripe_session_id' => $order->stripe_session_id,
                        'webhook_stripe_session_id' => $session->id ?? null,
                    ]);
                    return $order;
                }

                if (! $order->stripe_session_id) {
                    $order->stripe_session_id = $session->id;
                }

                if ($order->status !== 'paid') {
                    $order->status = 'paid';
                    $shouldDispatch = true;
                }

                $order->save();

                return $order;
            });

            if (! $order) {
                Log::warning('Stripe webhook checkout.session.completed order not found', [
                    'order_id' => (int) $orderId,
                    'stripe_event_id' => $event->id ?? null,
                    'stripe_session_id' => $session->id ?? null,
                ]);
                return response()->json(['status' => 'success']);
            }

            if ($order && $shouldDispatch) {
                event(new PaymentConfirmed($order));
            }
        }

        return response()->json(['status' => 'success']);
    }
}
