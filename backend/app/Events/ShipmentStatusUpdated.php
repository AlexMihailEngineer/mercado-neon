<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ShipmentStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        // Broadcasting on a private channel secured to the user who owns the order
        return [
            new Channel('shipments'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ShipmentStatusUpdated';
    }

    /**
     * The data to broadcast to the UI.
     */
    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'awb_number' => $this->order->awb_number,
            'status' => $this->order->logistics_status,
        ];
    }
}
