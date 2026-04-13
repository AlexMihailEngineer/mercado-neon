<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Customer Info
            $table->string('customer_name')->after('stripe_session_id');
            $table->string('customer_phone')->after('customer_name');
            $table->string('customer_email')->after('customer_phone');

            // Shipping Address (Strict RO nomenclature)
            $table->string('shipping_county')->after('customer_email');
            $table->string('shipping_city')->after('shipping_county');
            $table->text('shipping_address')->after('shipping_city');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'customer_name',
                'customer_phone',
                'customer_email',
                'shipping_county',
                'shipping_city',
                'shipping_address'
            ]);
        });
    }
};
