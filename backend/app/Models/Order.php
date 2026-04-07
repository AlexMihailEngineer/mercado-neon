<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Order extends Model
{
    protected $fillable = [
        'invoice_number',
        'total_amount_ron',
        'status',
        'stripe_session_id',
        'sameday_awb'
    ];

    /**
     * Data Casting
     */
    protected $casts = [
        'total_amount_ron' => 'float',
    ];

    /**
     * Helper to get EUR value for Stripe/Reporting
     * Calculated on the fly to reflect the 2026 Pivot.
     */
    public function getTotalAmountEurAttribute(): float
    {
        // Using the 5.00 RON/EUR rate
        return round($this->total_amount_ron / 5.00, 2);
    }
}
