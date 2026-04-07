<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        // 1. Strict Validation
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        // 2. Zero-Trust Math (Recalculate total server-side)
        $totalRon = 0.0;
        foreach ($validated['items'] as $reqItem) {
            $product = Product::select('price')->find($reqItem['id']);
            $totalRon += ($product->price * $reqItem['quantity']);
        }

        // 3. Generate Sequential B2B Invoice Number
        $lastOrder = Order::latest('id')->first();
        $nextId = $lastOrder ? $lastOrder->id + 1 : 1;
        $invoiceNumber = 'MN-2026-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

        // 4. Create the Order
        $order = Order::create([
            'invoice_number' => $invoiceNumber,
            'total_amount_ron' => round($totalRon, 2),
            'status' => 'pending'
        ]);

        // Optional Day 5 task: we could save the items to an `order_items` pivot table here

        // 5. Return to Dashboard (Inertia will see the new order in the pendingOrders prop)
        return back();
    }
}
