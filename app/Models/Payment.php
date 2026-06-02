<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'user_id',
        'traveler_id',
        'snap_token',
        'midtrans_order_id',
        'midtrans_transaction_id',
        'payment_type',
        'payment_channel',
        'va_number',
        'amount',
        'fee',
        'total_paid',
        'payment_status',
        'payment_reference',
        'reject_reason',
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

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function traveler()
    {
        return $this->belongsTo(Traveler::class);
    }
}
