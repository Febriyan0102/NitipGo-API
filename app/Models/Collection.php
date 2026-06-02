<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Collection extends Model
{
    use HasFactory;

    protected $fillable = [
        'trip_id',
        'name',
        'address',
        'collections_time',
        'map_url',
        'order'
    ];

    protected $casts = [
        'collections_time' => 'datetime:H:i'
    ];

    //relation
    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'collection_point_id');
    }
}