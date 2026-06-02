<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TravelerBooster extends Model
{
    use HasFactory;

    protected $fillable = [
        'traveler_id',
        'booster_id',
        'payment_booster_id',
        'start_date',
        'end_date',
        'orders_gained',
        'status'
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime'
    ];

    public function traveler()
    {
        return $this->belongsTo(Traveler::class);
    }

    public function booster()
    {
        return $this->belongsTo(Booster::class);
    }

    public function paymentBooster()
    {
        return $this->belongsTo(PaymentBooster::class);
    }
}