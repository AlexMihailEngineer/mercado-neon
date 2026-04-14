<?php

namespace App\Http\Controllers\Api;

use App\Events\ShipmentStatusUpdated;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FanCourierWebhookController extends Controller
{
    /**
     * Handle the incoming webhook from FAN Courier.
     */
    public function __invoke(Request $request)
    {
        // 1. Security Check: Authenticate the origin
        if (! $this->verifyWebhookOrigin($request)) {
            Log::warning('Unauthorized FAN Courier webhook attempt.', [
                'ip' => $request->ip(),
                'payload' => $request->all(),
            ]);

            // Fail securely without exposing internal logic
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // 2. Extract and Validate Payload
        // Note: Map these keys precisely to the FAN Courier API documentation
        $awbNumber = $request->input('awb');
        $currentStatus = $request->input('status'); // e.g., 'in_transit', 'delivered'
        $eventTimestamp = $request->input('timestamp', now());

        if (! $awbNumber || ! $currentStatus) {
            Log::error('FAN Courier Webhook: Missing critical payload data.', $request->all());

            return response()->json(['error' => 'Malformed payload'], 400);
        }

        // 3. Locate the Local Entity
        $order = Order::where('sameday_awb', $awbNumber)->first();

        if (! $order) {
            // Return 200 even if not found to prevent the provider from endlessly retrying a dead payload
            Log::info("FAN Courier Webhook: Received update for untracked AWB [{$awbNumber}].");

            return response()->json(['message' => 'AWB not tracked locally'], 200);
        }

        // 4. Mutate State
        $order->update([
            'status' => $currentStatus,
            'logistics_last_updated_at' => $eventTimestamp,
        ]);

        // 5. Trigger the Real-Time Broadcast
        // This decouples the webhook receipt from the WebSockets UI update layer
        ShipmentStatusUpdated::dispatch($order);

        // 6. Acknowledge Receipt Immediately
        return response()->json(['status' => 'success'], 200);
    }

    /**
     * Verifies that the payload genuinely originated from FAN Courier servers.
     */
    private function verifyWebhookOrigin(Request $request): bool
    {
        // Strategy A: Cryptographic Signature (Preferred if supported by provider)
        $signature = $request->header('X-Fan-Courier-Signature');
        $secret = config('services.fancourier.webhook_secret');

        if ($signature && $secret) {
            $computedSignature = hash_hmac('sha256', $request->getContent(), $secret);

            return hash_equals($computedSignature, $signature);
        }

        // Strategy B: IP Whitelisting (Common fallback for older logistics APIs)
        // Ensure these IPs are correctly sourced from FAN Courier's official technical documentation
        $allowedIps = [
            '172.19.0.1', // Example IP, replace with actual
        ];

        return in_array($request->ip(), $allowedIps);
    }
}
