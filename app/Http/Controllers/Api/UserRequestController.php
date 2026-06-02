<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User; 
use App\Models\Notification;
use App\Models\UserRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;

class UserRequestController extends Controller
{
    // SHOW ALL REQUEST USERS
    public function index(Request $request)
    {
        $requests = UserRequest::query()
            ->with('user:id,name,email')
            ->when($request->status, fn ($q) =>
                $q->where('status_requested', $request->status)
            )
            ->when($request->role, fn ($q) =>
                $q->where('requested_role', $request->role)
            )
            ->when($request->search, fn ($q) =>
                $q->where(function ($q) use ($request) {
                    $q->where('name', 'like', "%{$request->search}%")
                      ->orWhere('email', 'like', "%{$request->search}%")
                      ->orWhere('phone', 'like', "%{$request->search}%");
                })
            )
            ->latest()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Daftar request berhasil diambil',
            'data'    => $requests,
        ]);
    }

    // SHOW SINGLE REQUEST
    public function show($id)
    {
        $userRequest = UserRequest::with('user:id,name,email,status')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail request berhasil diambil',
            'data'    => $userRequest,
        ]);
    }

    // CREATE REQUEST (guest/public)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'email'          => [
                'required',
                'email',
                Rule::unique('user_requests', 'email'),
                Rule::unique('users', 'email'),
            ],
            'phone'          => [
                'required',
                'string',
                Rule::unique('user_requests', 'phone'),
                Rule::unique('users', 'phone'),
            ],
            'password'       => 'required|min:3',
            'requested_role' => ['required', Rule::in(['admin', 'customer'])],
        ]);

        $userRequest = UserRequest::create([
            'name'             => $validated['name'],
            'email'            => $validated['email'],
            'phone'            => $validated['phone'],
            'password'         => Hash::make($validated['password']),
            'requested_role'   => $validated['requested_role'],
            'status_requested' => 'pending',
        ]);
     // NOTIF KE ADMIN
        $admins = User::where('role', 'admin')->get();

        foreach ($admins as $admin) {

            Notification::create([
                'user_id' => $admin->id,

                'type' => 'verifikasi',
                'title' => 'Request User Baru',

                'message' => json_encode([
                    'name' => $userRequest->name,
                    'email' => $userRequest->email,
                    'role' => $userRequest->requested_role,
                    'status' => 'pending_verification',
                ]),

                'is_read' => false,
            ]);
        }


        return response()->json([
            'success' => true,
            'message' => 'Permintaan akun berhasil dikirim',
            'data'    => $userRequest,
        ], 201);
    }

    // APPROVE REQUEST ( ADMIN )
    public function approve(Request $request, $id)
    {
        $userRequest = UserRequest::whereIn('status_requested', ['pending', 'rejected'])
            ->findOrFail($id);

        if (User::where('email', $userRequest->email)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'User dengan email ini sudah terdaftar.',
            ], 409);
        }

        if ($userRequest->phone && User::where('phone', $userRequest->phone)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'User dengan nomor telepon ini sudah terdaftar.',
            ], 409);
        }

        $user = User::create([
            'name'     => $userRequest->name,
            'email'    => $userRequest->email,
            'phone'    => $userRequest->phone,
            'password' => $userRequest->password,
            'role'     => $userRequest->requested_role,
            'status'   => 'active',
        ]);

        // Delete user request after approve
        $userRequest->delete();

        return response()->json([
            'success' => true,
            'message' => 'Request berhasil disetujui dan akun telah dibuat.',
            'data'    => $user,
        ]);
    }

    // REJECT REQUEST
    public function reject($id)
    {
        $userRequest = UserRequest::where('status_requested', 'pending')
            ->findOrFail($id);

        $userRequest->update([
            'status_requested' => 'rejected',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Request berhasil ditolak.',
            'data'    => [
                'id'     => $userRequest->id,
                'name'   => $userRequest->name,
                'status' => $userRequest->status_requested,
            ],
        ]);
    }

    // DELETE REQUEST
    public function destroy($id)
    {
        $userRequest = UserRequest::where('status_requested', 'rejected')
            ->findOrFail($id);

        $userRequest->delete();

        return response()->json([
            'success' => true,
            'message' => 'Request berhasil dihapus permanen.',
        ]);
    }
}