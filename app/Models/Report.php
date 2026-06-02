<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'transaction_id',
        'customer_id',
        'traveler_id',
        'reporter_role',
        'title',
        'description',
        'dispute_priority',
        'dispute_status',
        'note',
        'traveler_note',
        'resolved_at'
    ];

    protected $casts = [
        'resolved_at' => 'datetime'
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