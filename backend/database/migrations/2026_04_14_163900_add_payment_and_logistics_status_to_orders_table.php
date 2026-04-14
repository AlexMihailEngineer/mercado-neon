<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_status')->default('pending')->after('status');
            $table->string('logistics_status')->nullable()->after('payment_status');
        });

        DB::table('orders')
            ->whereIn('status', ['pending', 'paid'])
            ->update(['payment_status' => DB::raw('status')]);

        DB::table('orders')
            ->whereIn('status', ['awb_generated', 'shipped', 'in_transit', 'out_for_delivery', 'delivered'])
            ->update([
                'payment_status' => 'paid',
                'logistics_status' => DB::raw('status'),
            ]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['payment_status', 'logistics_status']);
        });
    }
};
