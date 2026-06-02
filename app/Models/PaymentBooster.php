<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentBooster extends Model
{
    use HasFactory;

    protected $fillable = [
        'traveler_id',
        'booster_id',
        'amount',
        'fee',
        'total_paid',
        'snap_token',
        'midtrans_order_id',
        'midtrans_transaction_id',
        'payment_type',
        'payment_channel',
        'va_number',
        'payment_reference',
        'reject_reason',
        'status',
        'paid_at',
        'expired_at',
    ];

    protected $casts = [
        'amount'     => 'decimal:2',
        'fee'        => 'decimal:2',
        'total_paid' => 'decimal:2',
        'paid_at'    => 'datetime',
        'expired_at' => 'datetime',
    ];

    public function traveler()
    {
        return $this->belongsTo(Traveler::class);
    }

    public function booster()
    {
        return $this->belongsTo(Booster::class);
    }
}
