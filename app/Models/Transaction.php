<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'traveler_id',
        'customer_id',
        'trip_id',
        'pickup_point_id',
        'collection_point_id',
        'sku',
        'order_type',
        'name',
        'arrival_date',
        'quantity',
        'item_price',
        'destination_address',
        'notes',
        'description',
        'weight',
        'commission',
        'shipping_price',
        'price',
        'payment_proof',
        'paid_at',
        'price_confirmed',
        'image',
        'recipient_name',
        'recipient_phone',
        'status',
        'cancelled_at',
    ];

    protected $casts = [
        'arrival_date'   => 'date',
        'quantity'       => 'integer',
        'item_price'     => 'decimal:2',
        'commission'     => 'decimal:2',
        'shipping_price' => 'decimal:2',
        'price'          => 'decimal:2',
        'weight'         => 'decimal:2',
        'paid_at'        => 'datetime',
        'cancelled_at'   => 'datetime',
        'price_confirmed' => 'boolean',
    ];

    public function traveler()
    {
        return $this->belongsTo(Traveler::class);
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function pickupPoint()
    {
        return $this->belongsTo(Pickup::class, 'pickup_point_id');
    }

    public function collectionPoint()
    {
        return $this->belongsTo(Collection::class, 'collection_point_id');
    }

    public function rating()
    {
        return $this->hasOne(Rating::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function reports()
    {
        return $this->hasMany(Report::class);
    }
    public function orderProcess()
    {
        return $this->hasOne(OrderProcess::class);
    }
}