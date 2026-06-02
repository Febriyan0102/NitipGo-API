<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trip extends Model
{
    use HasFactory;

    protected $fillable = [
        'traveler_id',
        'code',
        'city',
        'destination',
        'departure_at',
        'estimated_arrival_at',
        'price',
        'capacity',
        'used_capacity',
        'description',
        'status',
        'orders_count',
        'is_tracking',
        'tracking_started_at',
        'tracking_finished_at',
        'origin_latitude',
        'origin_longitude',
        'destination_latitude',
        'destination_longitude'
    ];

    protected $casts = [
        'departure_at' => 'datetime',
        'estimated_arrival_at' => 'datetime',
        'tracking_started_at' => 'datetime',
        'tracking_finished_at' => 'datetime',
        'is_tracking' => 'boolean',
        'price' => 'decimal:2',
        'capacity' => 'decimal:2',
        'used_capacity' => 'decimal:2',
        'origin_latitude' => 'decimal:7',
        'origin_longitude' => 'decimal:7',
        'destination_latitude' => 'decimal:7',
        'destination_longitude' => 'decimal:7'
    ];

    //relation
    public function traveler()
    {
        return $this->belongsTo(Traveler::class);
    }

    public function pickups()
    {
        return $this->hasMany(Pickup::class);
    }

    public function collections()
    {
        return $this->hasMany(Collection::class);
    }

    public function trackings()
    {
        return $this->hasMany(TripTracking::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function reports()
    {
        return $this->hasMany(Report::class);
    }
}