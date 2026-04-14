<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory; // Added for testing
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'total_amount_ron',
        'status',
        'stripe_session_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'shipping_county',
        'shipping_city',
        'shipping_address',
        'sameday_awb',
        'logistics_last_updated_at',
    ];

    protected $casts = [
        'total_amount_ron' => 'decimal:2',
    ];

    protected $appends = ['awb_number', 'logistics_status'];

    /**
     * Helper to get EUR value for Stripe.
     * Calculated using the 1:5 ratio with round-half-up logic.
     */
    public function getTotalAmountEurAttribute(): float
    {
        $ronNormalized = number_format((float) $this->total_amount_ron, 2, '.', '');
        $ronCents = (int) str_replace('.', '', $ronNormalized);
        $eurCents = intdiv($ronCents, 5);

        if (($ronCents % 5) >= 3) {
            $eurCents++;
        }

        return $eurCents / 100;
    }

    /**
     * Accessor for frontend HUD display - maps sameday_awb to awb_number.
     */
    public function getAwbNumberAttribute(): ?string
    {
        return $this->sameday_awb;
    }

    /**
     * Accessor for frontend HUD display - maps status to logistics_status.
     */
    public function getLogisticsStatusAttribute(): string
    {
        return $this->status;
    }
}
