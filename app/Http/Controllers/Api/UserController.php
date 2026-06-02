<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    // SHOW ALL USERS AND FILTER
    public function index(Request $request)
    {
        $users = User::query()
            ->when($request->role, fn ($q) =>
                $q->where('role', $request->role)
            )
            ->when($request->status, fn ($q) =>
                $q->where('status', $request->status)
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
            'message' => 'Daftar user berhasil diambil',
            'data'    => $users,
        ]);
    }

    // SHOW USERS ( SINGLE USER )
    public function show($id)
    {
        $user = User::with([
            'setting',
            'ratings',
            'transactions',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail user berhasil diambil',
            'data'    => $user,
        ]);
    }

    // CREATE USER 
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => [
                'required',
                'email',
                Rule::unique('users', 'email'),
            ],
            'phone'    => [
                'required',
                'string',
                Rule::unique('users', 'phone'),
            ],
            'password' => 'required|min:3',
            'role'     => ['required', Rule::in(['admin', 'customer'])],
            'address'  => 'nullable|string',
        ]);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'phone'    => $validated['phone'],
            'password' => Hash::make($validated['password']),
            'role'     => $validated['role'],
            'address'  => $validated['address'] ?? null,
            'status'   => 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User berhasil dibuat',
            'data'    => $user,
        ], 201);
    }

    // UPDATE USERS DATA
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'phone'   => [
                'required',
                'string',
                Rule::unique('users', 'phone')->ignore($user->id),
            ],
            'role'    => ['required', Rule::in(['admin', 'customer'])],
            'status'  => ['required', Rule::in(['active', 'non_active'])],
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
            'message' => 'User berhasil diperbarui',
            'data'    => $user,
        ]);
    }

    // STATUS UPDATE
    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'non_active'])],
        ]);

        $user = User::findOrFail($id);

        if ($user->id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat mengubah status akun sendiri.',
            ], 403);
        }

        $user->update(['status' => $validated['status']]);

        return response()->json([
            'success' => true,
            'message' => 'Status user berhasil diperbarui',
            'data'    => [
                'id'     => $user->id,
                'name'   => $user->name,
                'status' => $user->status,
            ],
        ]);
    }

    // DELETE USERS
    public function destroy(Request $request, $id)
    {
        $user = User::findOrFail($id);

        if ($user->id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat menghapus akun sendiri.',
            ], 403);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User berhasil dihapus',
        ]);
    }
}