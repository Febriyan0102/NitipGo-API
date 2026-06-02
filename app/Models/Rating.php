<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rating extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'traveler_id',
        'customer_id',
        'rating',
        'review'
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function traveler()
    {
        return $this->belongsTo(Traveler::class);
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
}