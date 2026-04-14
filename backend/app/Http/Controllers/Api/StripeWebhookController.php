<?php

namespace App\Http\Controllers\Api;

use App\Events\PaymentConfirmed;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    /**
     * Handle the incoming Stripe webhook.
     */
    public function __invoke(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        try {
            // 1. Strict Security Check
            $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (SignatureVerificationException $e) {
            Log::error('STRIPE_WEBHOOK_SIG_FAIL: '.$e->getMessage());

            return response()->json(['error' => 'Invalid Signature'], 400);
        }

        // 2. Filter for relevant events
        if ($event->type === 'checkout.session.completed' || $event->type === 'checkout.session.async_payment_succeeded') {
            return $this->handleCheckoutSession($event->data->object);
        }

        return response()->json(['status' => 'ignored'], 200);
    }

    /**
     * Process the Checkout Session within a database transaction.
     */
    private function handleCheckoutSession(object $session)
    {
        $orderId = $session->metadata->order_id ?? null;

        if (! is_numeric($orderId)) {
            Log::warning('STRIPE_WEBHOOK_INVALID_METADATA: order_id missing.');

            return response()->json(['status' => 'success']);
        }

        $shouldDispatch = false;

        // 3. The Idempotency Engine: DB Transaction + Row Locking
        $order = DB::transaction(function () use ($orderId, $session, &$shouldDispatch) {
            // Find order and lock it so no other process can touch it until we finish
            $order = Order::query()->whereKey((int) $orderId)->lockForUpdate()->first();

            if (! $order) {
                return null;
            }

            // 4. Session Verification
            if ($order->stripe_session_id && $order->stripe_session_id !== $session->id) {
                Log::warning("STRIPE_WEBHOOK_SESSION_MISMATCH: order_id={$order->id}");

                return $order;
            }

            // 5. Amount & Currency Validation (Crucial for Scavenger platform integrity)
            if (! $this->isExpectedAmount($order, $session)) {
                Log::error("STRIPE_WEBHOOK_AMOUNT_FRAUD_TRIGGER: order_id={$order->id}");

                return $order;
            }

            // 6. Mutate State only if not already processed
            if ($order->payment_status !== 'paid') {
                $order->payment_status = 'paid';
                $order->status = 'paid';
                $order->stripe_session_id = $session->id;
                $order->save();

                $shouldDispatch = true;
            }

            return $order;
        });

        if ($order && $shouldDispatch) {
            // 7. Dispatch domain event to trigger Logistics and Real-time UI
            event(new PaymentConfirmed($order));
            Log::info("ORDER_PAID: Invoice {$order->invoice_number} finalized via Webhook.");
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Re-calculates expected EUR cents from the local RON amount.
     */
    private function expectedEurAmountInCents(Order $order): int
    {
        $ronCents = (int) round((float) $order->total_amount_ron * 100);
        // Assuming your 5:1 exchange strategy from Controller 1
        $amountInCents = intdiv($ronCents, 5);
        $remainder = $ronCents % 5;

        if ($remainder >= 3) {
            $amountInCents++;
        }

        return $amountInCents;
    }

    private function isExpectedAmount(Order $order, object $session): bool
    {
        $currency = strtolower((string) ($session->currency ?? ''));
        $amountTotal = (int) ($session->amount_total ?? 0);

        return $currency === 'eur' && $amountTotal === $this->expectedEurAmountInCents($order);
    }
}
