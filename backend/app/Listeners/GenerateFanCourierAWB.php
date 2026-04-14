<?php

namespace App\Listeners;

use App\Events\PaymentConfirmed;
use App\Events\ShipmentStatusUpdated;
use App\Services\FanCourierService;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

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
        if (! empty($order->awb_number)) {
            Log::info("AWB_ALREADY_EXISTS: Order {$order->id} already has AWB {$order->awb_number}");

            return;
        }

        $awbNumber = $this->fanCourier->generateAwb($order);

        $order->update([
            'awb_number' => $awbNumber,
            'logistics_status' => 'awb_generated',
            'status' => 'awb_generated',
        ]);

        $order->refresh();

        if (empty($order->awb_number)) {
            throw new Exception("AWB persistence failed for order {$order->id}.");
        }

        ShipmentStatusUpdated::dispatch($order);

        Log::info("ORDER_PROCESSED: AWB {$awbNumber} successfully attached to Order {$order->id}");
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
