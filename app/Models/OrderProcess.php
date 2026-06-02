<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderProcess extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'step',

        'original_item_price',
        'updated_item_price',
        'updated_total_price',
        'receipt_photo',
        'price_notes',

        // existing
        'accepted_at',
        'paid_at',
        'shipped_at',
        'completed_at',
        'cancelled_at',
        'cancel_reason',
    ];

    protected $casts = [
        'accepted_at'  => 'datetime',
        'paid_at'      => 'datetime',
        'shipped_at'   => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}