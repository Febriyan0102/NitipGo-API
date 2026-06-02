<?php

namespace App\Models;


use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'users';


    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'address',
        'role',
        'status',
        'profile_photo',
        'rejection_reason',
        'rejection_solution'
    ];


    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    // Access Token (Short lived & Long lived)
    public function createAccessToken(string $device = 'api', array $abilities = ['*'])
    {
        return $this->createToken($device, $abilities, now()->addMinutes(15));
    }

    public function createRefreshToken(string $device = 'api')
    {
        return $this->createToken($device . '_refresh', ['refresh'], now()->addDays(7));
    }

    //relation
    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'customer_id');
    }

    public function reports()
    {
        return $this->hasMany(Report::class, 'customer_id');
    }

    public function userRequests()
    {
        return $this->hasMany(UserRequest::class);
    }

    public function approvedTravelerRequests()
    {
        return $this->hasMany(TravelerRequest::class, 'approved_by');
    }

    public function systemSettingsUpdated()
    {
        return $this->hasMany(SystemSetting::class, 'updated_by');
    }

    //settings
    public function setting()
    {
        return $this->hasOne(UserSetting::class);
    }

    //ratings
    public function ratings()
    {
        return $this->hasMany(Rating::class, 'customer_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'user_id');
    }

    public function loginActivities()
    {
        return $this->hasMany(LoginActivity::class);
    }
}
