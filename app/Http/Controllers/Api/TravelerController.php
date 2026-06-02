<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Traveler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class TravelerController extends Controller
{
    // SHOW ALL TRAVELERS AND FILTER
    public function index(Request $request)
    {
        $travelers = Traveler::query()
            ->withCount([
                'transactions',
                'trips',
                'ratings',
                'reports',
                'withdrawRequests',
            ])
            ->when($request->status, fn ($q) =>
                $q->where('status', $request->status)
            )
            ->when($request->gender, fn ($q) =>
                $q->where('gender', $request->gender)
            )
            ->when($request->city, fn ($q) =>
                $q->where('city', $request->city)
            )
            ->when($request->province, fn ($q) =>
                $q->where('province', $request->province)
            )
            ->when($request->search, fn ($q) =>
                $q->where(function ($q) use ($request) {
                    $q->where('name', 'like', "%{$request->search}%")
                      ->orWhere('email', 'like', "%{$request->search}%")
                      ->orWhere('phone', 'like', "%{$request->search}%")
                      ->orWhere('ktp_number', 'like', "%{$request->search}%");
                })
            )
            ->latest()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Daftar traveler berhasil diambil',
            'data'    => $travelers,
        ]);
    }

    // SHOW SINGLE TRAVELER
    public function show($id)
    {
        $traveler = Traveler::with([
            'setting',
            'ratings',
            'transactions',
            'trips',
            'payoutAccounts',
            'withdrawRequests',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail traveler berhasil diambil',
            'data'    => $traveler,
        ]);
    }

    // CREATE TRAVELER
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'email'           => [
                'required',
                'email',
                Rule::unique('travelers', 'email'),
            ],
            'phone'           => [
                'required',
                'string',
                Rule::unique('travelers', 'phone'),
            ],
            'password'        => 'required|min:3',
            'city'            => 'required|string|max:255',
            'province'        => 'required|string|max:255',
            'address'         => 'required|string',
            'birth_date'      => 'required|date',
            'gender'          => ['required', Rule::in(['male', 'female'])],
            'ktp_number'      => [
                'required',
                'string',
                Rule::unique('travelers', 'ktp_number'),
            ],
            'ktp_photo'       => 'required|string',
            'selfie_with_ktp' => 'required|string',
            'pass_photo'      => 'required|string',
            'sim_card_photo'  => 'required|string',
        ]);

        $traveler = Traveler::create([
            'name'            => $validated['name'],
            'email'           => $validated['email'],
            'phone'           => $validated['phone'],
            'password'        => Hash::make($validated['password']),
            'role'            => 'traveler',
            'city'            => $validated['city'],
            'province'        => $validated['province'],
            'address'         => $validated['address'],
            'birth_date'      => $validated['birth_date'],
            'gender'          => $validated['gender'],
            'ktp_number'      => $validated['ktp_number'],
            'ktp_photo'       => $validated['ktp_photo'],
            'selfie_with_ktp' => $validated['selfie_with_ktp'],
            'pass_photo'      => $validated['pass_photo'],
            'sim_card_photo'  => $validated['sim_card_photo'],
            'status'          => 'active',
            'email_verified'  => false,
            'phone_verified'  => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Traveler berhasil dibuat',
            'data'    => $traveler,
        ], 201);
    }

    // UPDATE TRAVELER DATA
    public function update(Request $request, $id)
    {
        $traveler = Traveler::findOrFail($id);

        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'phone'           => [
                'required',
                'string',
                Rule::unique('travelers', 'phone')->ignore($traveler->id),
            ],
            'city'            => 'required|string|max:255',
            'province'        => 'required|string|max:255',
            'address'         => 'required|string',
            'birth_date'      => 'required|date',
            'gender'          => ['required', Rule::in(['male', 'female'])],
            'ktp_number'      => [
                'required',
                'string',
                Rule::unique('travelers', 'ktp_number')->ignore($traveler->id),
            ],
            'ktp_photo'       => 'nullable|string',
            'selfie_with_ktp' => 'nullable|string',
            'pass_photo'      => 'nullable|string',
            'profile_photo'   => 'nullable|string',
            'sim_card_photo'  => 'nullable|string',
            'status'          => ['required', Rule::in(['active', 'non_active'])],
            'password'        => 'nullable|min:3',
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $traveler->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Traveler berhasil diperbarui',
            'data'    => $traveler,
        ]);
    }

    // STATUS UPDATE
    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'non_active'])],
        ]);

        $traveler = Traveler::findOrFail($id);

        $traveler->update(['status' => $validated['status']]);

        return response()->json([
            'success' => true,
            'message' => 'Status traveler berhasil diperbarui',
            'data'    => [
                'id'     => $traveler->id,
                'name'   => $traveler->name,
                'status' => $traveler->status,
            ],
        ]);
    }

    // DELETE TRAVELER
    public function destroy($id)
    {
        $traveler = Traveler::findOrFail($id);

        //  Delete all photo files in storage
        $photos = [
            $traveler->ktp_photo,
            $traveler->selfie_with_ktp,
            $traveler->pass_photo,
            $traveler->sim_card_photo,
            $traveler->profile_photo,
        ];

        foreach ($photos as $photo) {
            if ($photo && Storage::disk('public')->exists($photo)) {
                Storage::disk('public')->delete($photo);
            }
        }

        $traveler->delete();

        return response()->json([
            'success' => true,
            'message' => 'Traveler berhasil dihapus',
        ]);
    }
}