<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSetting extends Model
{
    protected $table = 'user_settings';

    protected $fillable = [
        'user_id',
        'traveler_id',
        'notify_email',
        'notify_push',
        'notify_order',
        'notify_payment',
        'two_factor_enabled'
    ];

    protected $casts = [
        'notify_email' => 'boolean',
        'notify_push' => 'boolean',
        'notify_order' => 'boolean',
        'notify_payment' => 'boolean',
        'two_factor_enabled' => 'boolean'
    ];

    //relation
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function traveler()
    {
        return $this->belongsTo(Traveler::class);
    }
}