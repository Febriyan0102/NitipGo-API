<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TravelerRequest extends Model
{
    protected $table = 'traveler_requests';

    protected $fillable = [
        'approved_by',
        'traveler_id',
        'name',
        'email',
        'password',
        'phone',
        'city',
        'province',
        'address',
        'birth_date',
        'gender',
        'ktp_number',
        'ktp_photo',
        'selfie_with_ktp',
        'pass_photo',
        'sim_card_photo',
        'status_requested',
        'approved_at'
    ];

    protected $hidden = [
        'password'
    ];

    protected $casts = [
        'birth_date' => 'date',
        'approved_at' => 'datetime'
    ];

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function traveler()
    {
        return $this->belongsTo(Traveler::class);
    }
}