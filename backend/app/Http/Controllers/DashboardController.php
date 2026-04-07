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

            // Scout + Typesense call
            'searchResults' => Product::search($search)
                ->take(8)
                ->get(),

            'filters' => [
                'search' => $query, // Keep the actual query (or null) for the UI input
            ],

            'systemStats' => [
                'active_awbs' => 12,
                'match_accuracy' => 98.2,
                'is_anaf_synced' => true,
            ]
        ]);
    }
}
