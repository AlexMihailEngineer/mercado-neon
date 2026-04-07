<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique(); // Needed for ANAF e-Factura
            $table->decimal('total_amount_ron', 12, 2); // Store original RON
            $table->string('status')->default('pending'); // pending, paid, processing, shipped

            // Stripe Specifics
            $table->string('stripe_session_id')->nullable();

            // Sameday Logistics (for Day 5)
            $table->string('sameday_awb')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
