<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('orders')
            ->whereNull('logistics_status')
            ->whereIn('status', ['awb_generated', 'shipped', 'in_transit', 'out_for_delivery', 'delivered'])
            ->update(['logistics_status' => DB::raw('status')]);

        DB::table('orders')
            ->whereNull('payment_status')
            ->whereIn('status', ['pending', 'paid'])
            ->update(['payment_status' => DB::raw('status')]);

        DB::table('orders')
            ->whereNull('payment_status')
            ->whereIn('status', ['awb_generated', 'shipped', 'in_transit', 'out_for_delivery', 'delivered'])
            ->update(['payment_status' => 'paid']);
    }

    public function down(): void
    {
        // No-op: destructive to revert data changes.
    }
};
