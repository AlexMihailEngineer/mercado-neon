<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

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
        $toBani = static function (mixed $value): int {
            $raw = str_replace(',', '.', (string) $value);
            [$whole, $fraction] = array_pad(explode('.', $raw, 2), 2, '0');
            $whole = preg_replace('/\D/', '', $whole) ?: '0';
            $fraction = preg_replace('/\D/', '', $fraction) ?: '0';
            $fraction = substr(str_pad($fraction, 2, '0'), 0, 2);

            return ((int) $whole * 100) + (int) $fraction;
        };

        $totalBani = 0;
        $productIds = array_map(static fn(array $item): int => (int) $item['id'], $validated['items']);
        $pricesById = Product::query()
            ->whereIn('id', $productIds)
            ->pluck('price', 'id');

        foreach ($validated['items'] as $reqItem) {
            $priceBani = $toBani($pricesById[(int) $reqItem['id']] ?? '0');
            $totalBani += ($priceBani * (int) $reqItem['quantity']);
        }

        $totalRon = intdiv($totalBani, 100) . '.' . str_pad((string) ($totalBani % 100), 2, '0', STR_PAD_LEFT);

        $order = null;
        $attempts = 0;

        while ($order === null && $attempts < 3) {
            $attempts++;

            try {
                $order = DB::transaction(function () use ($totalRon) {
                    // 3. Generate Sequential B2B Invoice Number
                    $lastOrder = Order::query()
                        ->select('id')
                        ->latest('id')
                        ->lockForUpdate()
                        ->first();

                    $nextId = $lastOrder ? $lastOrder->id + 1 : 1;
                    $invoiceNumber = 'MN-2026-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

                    // 4. Create the Order
                    return Order::create([
                        'invoice_number' => $invoiceNumber,
                        'total_amount_ron' => round($totalRon, 2),
                        'status' => 'pending'
                    ]);
                }, 3);
            } catch (QueryException $e) {
                $order = null;
            }
        }

        if ($order === null) {
            return back()->withErrors(['error' => 'Could not create order.']);
        }

        // Optional Day 5 task: we could save the items to an `order_items` pivot table here

        // 5. Return to Dashboard (Inertia will see the new order in the pendingOrders prop)
        return back();
    }
}
