<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Traveler; 
use App\Models\Notification;
use App\Models\User;
use App\Models\TravelerRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use App\Mail\TravelerApprovedMail;
use App\Mail\TravelerRejectedMail;

class TravelerRequestController extends Controller
{
    // SHOW ALL TRAVELER REQUESTS AND FILTER
    public function index(Request $request)
    {
        $requests = TravelerRequest::query()
            ->with([
                'approver:id,name,email',
                'traveler:id,name,email,status',
            ])
            ->when($request->status, fn ($q) =>
                $q->where('status_requested', $request->status)
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
            'message' => 'Daftar traveler request berhasil diambil',
            'data'    => $requests,
        ]);
    }

    // SHOW SINGLE TRAVELER REQUEST
    public function show($id)
    {
        $travelerRequest = TravelerRequest::with([
            'approver:id,name,email',
            'traveler:id,name,email,status',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail traveler request berhasil diambil',
            'data'    => $travelerRequest,
        ]);
    }

    // CREATE TRAVELER REQUEST (public/guest)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'email'           => [
                'required',
                'email',
                Rule::unique('traveler_requests', 'email'),
                Rule::unique('travelers', 'email'),
            ],
            'phone'           => [
                'required',
                'string',
                Rule::unique('traveler_requests', 'phone'),
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
                Rule::unique('traveler_requests', 'ktp_number'),
                Rule::unique('travelers', 'ktp_number'),
            ],
            'ktp_photo'       => 'required|string',
            'selfie_with_ktp' => 'required|string',
            'pass_photo'      => 'required|string',
            'sim_card_photo'  => 'required|string',
        ]);

        $travelerRequest = TravelerRequest::create([
            'name'             => $validated['name'],
            'email'            => $validated['email'],
            'phone'            => $validated['phone'],
            'password'         => Hash::make($validated['password']),
            'city'             => $validated['city'],
            'province'         => $validated['province'],
            'address'          => $validated['address'],
            'birth_date'       => $validated['birth_date'],
            'gender'           => $validated['gender'],
            'ktp_number'       => $validated['ktp_number'],
            'ktp_photo'        => $validated['ktp_photo'],
            'selfie_with_ktp'  => $validated['selfie_with_ktp'],
            'pass_photo'       => $validated['pass_photo'],
            'sim_card_photo'   => $validated['sim_card_photo'],
            'status_requested' => 'pending',
        ]); 
        
       // NOTIF KE ADMIN
        $admins = User::where('role', 'admin')->get();

        foreach ($admins as $admin) {

            Notification::create([
                'user_id' => $admin->id,

                'type' => 'verifikasi',
                'title' => 'Pengajuan Traveler Baru',

                'message' => json_encode([
                    'traveler_name' => $travelerRequest->name,
                    'email' => $travelerRequest->email,
                    'status' => 'pending_verification',
                ]),

                'is_read' => false,
            ]);
        }

        
        return response()->json([
            'success' => true,
            'message' => 'Permintaan akun traveler berhasil dikirim',
            'data'    => $travelerRequest,
        ], 201);
    }

    // APPROVE TRAVELER REQUEST (admin)
    public function approve(Request $request, $id)
    {
        $travelerRequest = TravelerRequest::whereIn('status_requested', ['pending', 'rejected'])
            ->findOrFail($id);

        if (Traveler::where('email', $travelerRequest->email)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Traveler dengan email ini sudah terdaftar.',
            ], 409);
        }

        if (Traveler::where('phone', $travelerRequest->phone)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Traveler dengan nomor telepon ini sudah terdaftar.',
            ], 409);
        }

        if (Traveler::where('ktp_number', $travelerRequest->ktp_number)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Traveler dengan nomor KTP ini sudah terdaftar.',
            ], 409);
        }

        $traveler = Traveler::create([
            'name'            => $travelerRequest->name,
            'email'           => $travelerRequest->email,
            'phone'           => $travelerRequest->phone,
            'password'        => $travelerRequest->password,
            'role'            => 'traveler',
            'city'            => $travelerRequest->city,
            'province'        => $travelerRequest->province,
            'address'         => $travelerRequest->address,
            'birth_date'      => $travelerRequest->birth_date,
            'gender'          => $travelerRequest->gender,
            'ktp_number'      => $travelerRequest->ktp_number,
            'ktp_photo'       => $travelerRequest->ktp_photo,
            'selfie_with_ktp' => $travelerRequest->selfie_with_ktp,
            'pass_photo'      => $travelerRequest->pass_photo,
            'sim_card_photo'  => $travelerRequest->sim_card_photo,
            'profile_photo'   => $travelerRequest->pass_photo,
            'status'          => 'active',
            'email_verified'  => false,
            'phone_verified'  => false,
        ]);

        $travelerRequest->update([
            'traveler_id'      => $traveler->id,
            'approved_by'      => $request->user()->id,
            'status_requested' => 'approved',
            'approved_at'      => now(),
        ]);

        $travelerName  = $travelerRequest->name;
        $travelerEmail = $travelerRequest->email;

        // Hapus request
        $travelerRequest->delete();

        // send email message
        Mail::to($travelerEmail)->send(
            new TravelerApprovedMail($travelerName, $travelerEmail)
        );

        return response()->json([
            'success' => true,
            'message' => 'Request traveler berhasil disetujui dan akun telah dibuat.',
            'data'    => $traveler,
        ]);
    }

    // REJECT TRAVELER REQUEST
    public function reject(Request $request, $id)
    {
        $validated = $request->validate([
            'reason'   => 'required|string|max:1000',
            'solution' => 'required|string|max:1000',
        ]);

        $travelerRequest = TravelerRequest::where('status_requested', 'pending')
            ->findOrFail($id);

        $travelerRequest->update([
            'status_requested'  => 'rejected',
            'rejection_reason'  => $validated['reason'],
            'rejection_solution'=> $validated['solution'],
        ]);

        // Send mail message after reject traveler
        Mail::to($travelerRequest->email)
            ->send(new TravelerRejectedMail(
                $travelerRequest,
                $validated['reason'],
                $validated['solution'],
            ));

        return response()->json([
            'success' => true,
            'message' => 'Request traveler berhasil ditolak.',
            'data'    => $travelerRequest,
        ]);
    }

    // DELETE TRAVELER REQUEST
   public function destroy($id)
    {
        $travelerRequest = TravelerRequest::where('status_requested', 'rejected')
            ->findOrFail($id);

        // Delete all photo files in storage
        $photos = [
            $travelerRequest->ktp_photo,
            $travelerRequest->selfie_with_ktp,
            $travelerRequest->pass_photo,
            $travelerRequest->sim_card_photo,
        ];

        foreach ($photos as $photo) {
            if ($photo && Storage::disk('public')->exists($photo)) {
                Storage::disk('public')->delete($photo);
            }
        }

        $travelerRequest->delete();

        return response()->json([
            'success' => true,
            'message' => 'Request traveler berhasil dihapus permanen.',
        ]);
    }
}