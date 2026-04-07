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

        if ($event->type === 'checkout.session.completed' || $event->type === 'checkout.session.async_payment_succeeded') {
            $session = $event->data->object;
            $orderId = $session->metadata->order_id ?? null;

            if ($orderId) {
                $order = Order::find($orderId);

                if (!$order) {
                    return response()->json(['status' => 'processed'], 200);
                }

                if ($order->stripe_session_id !== null && $order->stripe_session_id !== $session->id) {
                    Log::warning("STRIPE_WEBHOOK_SESSION_MISMATCH: order_id={$order->id}");
                    return response()->json(['status' => 'processed'], 200);
                }

                if (!$this->isExpectedCheckoutSessionAmount($order, $session)) {
                    Log::warning("STRIPE_WEBHOOK_AMOUNT_MISMATCH: order_id={$order->id}");
                    return response()->json(['status' => 'processed'], 200);
                }

                if ($event->type === 'checkout.session.completed' && ($session->payment_status ?? null) !== 'paid') {
                    Log::info("STRIPE_WEBHOOK_NOT_PAID_YET: order_id={$order->id}");
                    return response()->json(['status' => 'processed'], 200);
                }

                $updated = Order::query()
                    ->whereKey($order->id)
                    ->where('status', '!=', 'paid')
                    ->update(['status' => 'paid']);

                if ($updated === 1) {
                    $order->refresh();
                    event(new PaymentConfirmed($order));
                    Log::info("ORDER_PAID: Invoice {$order->invoice_number} settled.");
                }
            }
        }

        if ($event->type === 'payment_intent.succeeded') {
            $paymentIntent = $event->data->object;
            $orderId = $paymentIntent->metadata->order_id ?? null;

            if ($orderId) {
                $order = Order::find($orderId);

                if ($order) {
                    if (!$this->isExpectedPaymentIntentAmount($order, $paymentIntent)) {
                        Log::warning("STRIPE_WEBHOOK_INTENT_AMOUNT_MISMATCH: order_id={$order->id}");
                        return response()->json(['status' => 'processed'], 200);
                    }

                    $updated = Order::query()
                        ->whereKey($order->id)
                        ->where('status', '!=', 'paid')
                        ->update(['status' => 'paid']);

                    if ($updated === 1) {
                        $order->refresh();
                        event(new PaymentConfirmed($order));
                        Log::info("ORDER_PAID: Invoice {$order->invoice_number} settled.");
                    }
                }
            }
        }

        return response()->json(['status' => 'processed'], 200);
    }

    private function expectedEurAmountInCents(Order $order): int
    {
        $ronNormalized = number_format((float) $order->total_amount_ron, 2, '.', '');
        $ronCents = (int) str_replace('.', '', $ronNormalized);
        $amountInCents = intdiv($ronCents, 5);
        $remainder = $ronCents % 5;

        if ($remainder >= 3) {
            $amountInCents++;
        }

        return $amountInCents;
    }

    private function isExpectedCheckoutSessionAmount(Order $order, object $session): bool
    {
        $currency = strtolower((string) ($session->currency ?? ''));

        if ($currency !== 'eur') {
            return false;
        }

        $amountTotal = isset($session->amount_total) ? (int) $session->amount_total : null;

        if ($amountTotal === null) {
            return false;
        }

        return $amountTotal === $this->expectedEurAmountInCents($order);
    }

    private function isExpectedPaymentIntentAmount(Order $order, object $paymentIntent): bool
    {
        $currency = strtolower((string) ($paymentIntent->currency ?? ''));

        if ($currency !== 'eur') {
            return false;
        }

        $amount = null;
        if (isset($paymentIntent->amount_received)) {
            $amount = (int) $paymentIntent->amount_received;
        } elseif (isset($paymentIntent->amount)) {
            $amount = (int) $paymentIntent->amount;
        }

        if ($amount === null) {
            return false;
        }

        return $amount === $this->expectedEurAmountInCents($order);
    }
}
