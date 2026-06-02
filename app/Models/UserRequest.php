<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class UserRequest extends Model
{
    protected $table = 'user_requests';

    protected $fillable = [
        'user_id',
        'name',
        'email',
        'phone',
        'password',
        'requested_role',
        'status_requested',
        'approved_at'
    ];

    protected $hidden = [
        'password'
    ];

    protected $casts = [
        'approved_at' => 'datetime'
    ];
    

    //relation
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}