<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
        'total_amount_ron' => 'decimal:2',
    ];

    /**
     * Helper to get EUR value for Stripe/Reporting
     * Calculated on the fly to reflect the 2026 Pivot.
     */
    public function getTotalAmountEurAttribute(): float
    {
        $ronNormalized = number_format((float) $this->total_amount_ron, 2, '.', '');
        $ronCents = (int) str_replace('.', '', $ronNormalized);
        $eurCents = intdiv($ronCents, 5);
        $remainder = $ronCents % 5;

        if ($remainder >= 3) {
            $eurCents++;
        }

        return $eurCents / 100;
    }
}
