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
        // 1. Strict Validation - Including new shipping requirements
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],

            // New Logistics Fields
            'customer_name'    => ['required', 'string', 'max:255'],
            'customer_phone'   => ['required', 'string', 'regex:/^07[0-9]{8}$/'], // Strict RO mobile format
            'customer_email'   => ['required', 'email', 'max:255'],
            'shipping_county'  => ['required', 'string', 'max:100'],
            'shipping_city'    => ['required', 'string', 'max:100'],
            'shipping_address' => ['required', 'string', 'max:500'],
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

        // Convert back to decimal for storage
        $totalRon = $totalBani / 100;

        $order = null;
        $attempts = 0;

        while ($order === null && $attempts < 3) {
            $attempts++;

            try {
                $order = DB::transaction(function () use ($totalRon, $validated) {
                    // 3. Generate Sequential B2B Invoice Number
                    $lastOrder = Order::query()
                        ->select('id')
                        ->latest('id')
                        ->lockForUpdate()
                        ->first();

                    $nextId = $lastOrder ? $lastOrder->id + 1 : 1;
                    $invoiceNumber = 'MN-2026-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

                    // 4. Create the Order with all required fields
                    return Order::create([
                        'invoice_number'   => $invoiceNumber,
                        'total_amount_ron' => $totalRon,
                        'status'           => 'pending',

                        // Passing the validated logistics data
                        'customer_name'    => $validated['customer_name'],
                        'customer_phone'   => $validated['customer_phone'],
                        'customer_email'   => $validated['customer_email'],
                        'shipping_county'  => $validated['shipping_county'],
                        'shipping_city'    => $validated['shipping_city'],
                        'shipping_address' => $validated['shipping_address'],
                    ]);
                }, 3);
            } catch (QueryException $e) {
                $order = null;
            }
        }

        if ($order === null) {
            return back()->withErrors(['error' => 'Could not create order due to a database conflict.']);
        }

        return back();
    }
}
