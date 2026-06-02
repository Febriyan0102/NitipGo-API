<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booster extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'price',
        'duration',
        'slots',
        'priority',
        'color',
        'description',
        'active'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'active' => 'boolean'
    ];

    public function travelerBoosters()
    {
        return $this->hasMany(TravelerBooster::class);
    }

    public function payments()
    {
        return $this->hasMany(PaymentBooster::class);
    }
}