<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Rating;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    // SHOW PROFILE
    public function show(Request $request)
    {
        $user = $request->user();

        // ROLE TRAVELER
        if ($user instanceof \App\Models\Traveler) {
            $data = [
                'id'               => $user->id,
                'name'             => $user->name,
                'email'            => $user->email,
                'phone'            => $user->phone,
                'city'             => $user->city,
                'province'         => $user->province,
                'address'          => $user->address,
                'birth_date'       => $user->birth_date,
                'gender'           => $user->gender,
                'ktp_number'       => $user->ktp_number,
                'ktp_photo'        => $user->ktp_photo,
                'selfie_with_ktp'  => $user->selfie_with_ktp,
                'pass_photo'       => $user->pass_photo,
                'sim_card_photo'   => $user->sim_card_photo,
                'profile_photo'    => $user->profile_photo,
                'status'           => $user->status,
                'email_verified'   => $user->email_verified,
                'phone_verified'   => $user->phone_verified,
                'created_at'       => $user->created_at,
                'stats'            => [
                    'total_trips'       => $user->trips()->count(),
                    'total_transactions'=> $user->transactions()->count(),
                    'rating'             => round($user->ratings()->avg('rating') ?? 0, 1),
                    'total_ratings'      => $user->ratings()->count(),
                    'total_withdraw'    => $user->withdrawRequests()->count(),
                ],
            ];

            return response()->json([
                'success' => true,
                'role'    => 'traveler',
                'data'    => $data,
            ]);
        }

        // ADMIN AND CUSTOMER
        $data = [
            'id'            => $user->id,
            'name'          => $user->name,
            'email'         => $user->email,
            'phone'         => $user->phone,
            'address'       => $user->address,
            'role'          => $user->role,
            'status'        => $user->status,
            'profile_photo' => $user->profile_photo,
            'created_at'    => $user->created_at,
            'stats'         => null,
        ];

        // Stats 
        if ($user->role === 'customer') {
            $data['stats'] = [
                'total_orders'  => $user->transactions()->count(),
                'rating_given'  => $user->ratings()->count(),
                'months_active' => $user->created_at
                    ? (int) now()->diffInMonths($user->created_at)
                    : 0,
            ];
        }

        // Stats khusus admin
        if ($user->role === 'admin') {
            $data['stats'] = [
                'settings_updated' => $user->systemSettingsUpdated()->count(),
                'travelers_approved' => $user->approvedTravelerRequests()
                    ->where('status_requested', 'approved')
                    ->count(),
            ];
        }

        return response()->json([
            'success' => true,
            'role'    => $user->role,
            'data'    => $data,
        ]);
    }

    // UPDATE PROFILE
    public function update(Request $request)
    {
        $user = $request->user();

        // ROLE TRAVELER
        if ($user instanceof \App\Models\Traveler) {
            $validated = $request->validate([
                'name'     => 'sometimes|string|max:255',
                'phone'    => [
                    'sometimes',
                    'string',
                    Rule::unique('travelers', 'phone')->ignore($user->id),
                ],
                'city'     => 'sometimes|string|max:255',
                'province' => 'sometimes|string|max:255',
                'address'  => 'sometimes|string',
                'password' => 'nullable|min:3',
            ]);

            if (!empty($validated['password'])) {
                $validated['password'] = Hash::make($validated['password']);
            } else {
                unset($validated['password']);
            }

            $user->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Profil traveler berhasil diperbarui',
                'data'    => $user,
            ]);
        }

        // ADMIN AND CUSTOMER
        $validated = $request->validate([
            'name'    => 'sometimes|string|max:255',
            'phone'   => [
                'sometimes',
                'string',
                Rule::unique('users', 'phone')->ignore($user->id),
            ],
            'address' => 'nullable|string',
            'password'=> 'nullable|min:3',
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diperbarui',
            'data'    => $user,
        ]);
    }

    // UPDATE PHOTO PROFILE
    public function updatePhoto(Request $request)
    {
        $request->validate([
            'profile_photo' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $user = $request->user();
        if ($user instanceof \App\Models\Traveler) {
            if ($user->profile_photo) {
                Storage::disk('public')->delete($user->profile_photo);
            }

            $path = $request->file('profile_photo')
                ->store('profile_photos/travelers', 'public');

            $user->profile_photo = $path;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Foto profil traveler berhasil diperbarui',
                'data'    => ['profile_photo' => $path],
            ]);
        }

        // User (admin/customer)
        if ($user->profile_photo) {
            Storage::disk('public')->delete($user->profile_photo);
        }

        $path = $request->file('profile_photo')
            ->store('profile_photos', 'public');

        $user->profile_photo = $path;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Foto profil berhasil diperbarui',
            'data'    => ['profile_photo' => $path],
        ]);
    }

    // DELETE ACCOUNT
    public function destroy(Request $request)
    {
        $user = $request->user();

        if ($user instanceof \App\Models\User && $user->role === 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Akun admin tidak dapat dihapus sendiri.',
            ], 403);
        }

        if ($user instanceof \App\Models\User && $user->profile_photo) {
            Storage::disk('public')->delete($user->profile_photo);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Akun berhasil dihapus',
        ]);
    }
}