<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TravelerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'email'           => $this->email,
            'phone'           => $this->phone,
            'role'            => 'traveler',
            'city'            => $this->city,
            'province'        => $this->province,
            'address'         => $this->address,
            'birth_date'      => $this->birth_date,
            'gender'          => $this->gender,
            'ktp_number'      => $this->ktp_number,
            'ktp_photo'       => $this->ktp_photo,
            'selfie_with_ktp' => $this->selfie_with_ktp,
            'pass_photo'      => $this->pass_photo,
            'sim_card_photo'  => $this->sim_card_photo,
            'profile_photo'   => $this->profile_photo,
            'status'          => $this->status,
            'email_verified'  => $this->email_verified,
            'phone_verified'  => $this->phone_verified,
            'created_at'      => $this->created_at,
        ];
    }
}