<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayoutAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'traveler_id',
        'payout_type',
        'provider',
        'account_name',
        'account_number',
        'is_default'
    ];

    protected $casts = [
        'is_default' => 'boolean'
    ];

    public function traveler()
    {
        return $this->belongsTo(Traveler::class);
    }

    public function withdrawRequests()
    {
        return $this->hasMany(WithdrawRequest::class);
    }
}