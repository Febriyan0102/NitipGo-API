<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdvertisementPayment extends Model
{
    protected $fillable = [
        'code', 'advertisement_id', 'partner_name', 'partner_contact',
        'amount', 'package', 'duration_days',
        'status', 'snap_token', 'order_id',
        'payment_type', 'paid_at', 'midtrans_response',
    ];

    protected $casts = [
        'paid_at'           => 'datetime',
        'midtrans_response' => 'array',
    ];

    public function advertisement(): BelongsTo
    {
        return $this->belongsTo(Advertisement::class);
    }
}