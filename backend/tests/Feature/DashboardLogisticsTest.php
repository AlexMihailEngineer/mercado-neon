<?php

use App\Models\Order;
use App\Models\User;

use function Pest\Laravel\actingAs;

it('shows pending orders by payment_status and active shipments by logistics_status', function () {
    $user = User::factory()->create();

    $pending = Order::factory()->create([
        'payment_status' => 'pending',
        'status' => 'pending',
    ]);

    $activeShipment = Order::factory()->create([
        'payment_status' => 'paid',
        'logistics_status' => 'in_transit',
        'status' => 'in_transit',
        'awb_number' => 'AWB123',
    ]);

    $deliveredShipment = Order::factory()->create([
        'payment_status' => 'paid',
        'logistics_status' => 'delivered',
        'status' => 'delivered',
        'awb_number' => 'AWB999',
    ]);

    $response = actingAs($user)->get(route('dashboard'));

    $response->assertOk();

    $response->assertInertia(
        fn ($page) => $page
            ->component('Dashboard')
            ->has('pendingOrders', 1)
            ->where('pendingOrders.0.id', $pending->id)
            ->has('activeShipments', 1)
            ->where('activeShipments.0.id', $activeShipment->id)
    );
});
