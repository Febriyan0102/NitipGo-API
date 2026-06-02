<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pickup extends Model
{
    use HasFactory;

    protected $fillable = [
        'trip_id',
        'name',
        'address',
        'pickup_time',
        'map_url',
        'order'
    ];

    protected $casts = [
        'pickup_time' => 'datetime:H:i'
    ];

    //relation
    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'pickup_point_id');
    }
}