<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Handle the Scavenger Dashboard view and Search Logic.
     */
    public function index(Request $request): Response
    {
        // Ensure we never pass null or empty string to Typesense
        // Use '*' as the wildcard for "match all"
        $query = $request->query('search');
        $search = empty($query) ? '*' : $query;

        return Inertia::render('Dashboard', [
            'pendingOrders' => Order::where('status', 'pending')
                ->latest()
                ->take(10)
                ->get(),

            // NEW: Active Shipments for the FAN Courier HUD
            'activeShipments' => Order::whereNotNull('sameday_awb')
                ->whereIn('status', ['paid', 'awb_generated', 'shipped']) // <-- UPDATED LINE
                ->latest()
                ->get(),

            // Scout + Typesense call
            'searchResults' => Product::search($search)
                ->take(8)
                ->get(),

            'filters' => [
                'search' => $query, // Keep the actual query (or null) for the UI input
            ],

            'systemStats' => [
                // Dynamic count of generated AWBs
                'active_awbs' => Order::whereNotNull('sameday_awb')->count(),
                'match_accuracy' => 98.2,
                'is_anaf_synced' => true,
            ],
        ]);
    }
}
