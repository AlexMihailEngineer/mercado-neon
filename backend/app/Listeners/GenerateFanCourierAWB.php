<?php

namespace App\Listeners;

use App\Events\PaymentConfirmed;
use App\Services\FanCourierService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Exception;

class GenerateFanCourierAWB implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The number of times the queued job may be attempted.
     * Essential for network calls to external logistics APIs.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     * Exponential backoff: waits 30s, then 1m, then 2m.
     */
    public array $backoff = [30, 60, 120];

    protected FanCourierService $fanCourier;

    /**
     * Inject the service via the constructor.
     */
    public function __construct(FanCourierService $fanCourier)
    {
        $this->fanCourier = $fanCourier;
    }

    /**
     * Handle the event.
     */
    public function handle(PaymentConfirmed $event): void
    {
        $order = $event->order;

        // Idempotency check: Don't generate a new AWB if one already exists
        if (!empty($order->awb_number)) {
            Log::info("AWB_ALREADY_EXISTS: Order {$order->id} already has AWB {$order->awb_number}");
            return;
        }

        try {
            // Call the service we built previously
            $awbNumber = $this->fanCourier->generateAwb($order);

            // Persist the AWB to the database
            $order->update([
                'sameday_awb' => $awbNumber,
                'status' => 'awb_generated',
            ]);

            Log::info("ORDER_PROCESSED: AWB {$awbNumber} successfully attached to Order {$order->id}");
        } catch (Exception $e) {
            // Log the failure
            Log::error("LISTENER_FAN_COURIER_FAIL: Failed to generate AWB for Order {$order->id}. Error: " . $e->getMessage());

            // Release the job back onto the queue with a delay (triggers the backoff array)
            $this->release($this->backoff[$this->attempts() - 1] ?? 300);
        }
    }

    /**
     * Handle a job failure after all retries are exhausted.
     */
    public function failed(PaymentConfirmed $event, Exception $exception): void
    {
        // Here you would typically trigger an alert to a Slack/Discord channel
        // or send an email to the logistics manager so they can manually intervene.
        Log::critical("MANUAL_INTERVENTION_REQUIRED: AWB generation permanently failed for Order {$event->order->id}.");
    }
}
