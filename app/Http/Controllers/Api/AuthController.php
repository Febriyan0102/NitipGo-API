<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Resources\TravelerResource;
use App\Models\User;
use App\Models\Traveler;
use App\Models\SystemSetting;
use App\Models\LoginActivity;
use Illuminate\Http\Request;
use App\Models\UserRequest;
use App\Models\TravelerRequest;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Notifications\NewDeviceLoginNotification;
use App\Mail\ResetPasswordMail;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    // Google redirect login
    public function googleRedirect()
    {
        return Socialite::driver('google')
            ->stateless()
            ->redirect();
    }

    // Google callback (response google)
    public function googleCallback(Request $request)
    {
        try {
            if (!$request->has('code')) {
                $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
                return redirect("{$frontendUrl}/login?error=google_cancelled");
            }

            $googleUser = Socialite::driver('google')
                ->stateless()
                ->user();

            $user = User::where('email', $googleUser->email)->first();

            if ($user && $user->status !== 'active') {
                $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
                return redirect("{$frontendUrl}/login?error=account_{$user->status}");
            }

            if (!$user) {
                $existingRequest = \App\Models\UserRequest::where('email', $googleUser->email)->first();

                if ($existingRequest) {
                    $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
                    return redirect("{$frontendUrl}/login?error=pending_approval");
                }

                \App\Models\UserRequest::create([
                    'name'             => $googleUser->name,
                    'email'            => $googleUser->email,
                    'phone'            => null,
                    'password'         => Hash::make(\Illuminate\Support\Str::random(16)),
                    'requested_role'   => 'customer',
                    'status_requested' => 'pending',
                ]);

                $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
                return redirect("{$frontendUrl}/login?error=pending_approval");
            }

            $user->tokens()->delete();

            $accessToken  = $user->createAccessToken('google_login', ['*']);
            $refreshToken = $user->createRefreshToken('google_login');

            // Redirect to frontend
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
            return redirect("{$frontendUrl}/auth/google/callback?access_token={$accessToken->plainTextToken}&refresh_token={$refreshToken->plainTextToken}");

        } catch (\Exception $e) {
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
            $errorMsg = urlencode($e->getMessage());
            return redirect("{$frontendUrl}/login?error=google_failed&msg={$errorMsg}");
        }
    }

    // Google token (untuk mobile/SPA)
    public function googleTokenLogin(Request $request)
    {
        $request->validate(['id_token' => 'required|string']);

        $response = Http::get('https://oauth2.googleapis.com/tokeninfo', [
            'id_token' => $request->id_token
        ]);

        if ($response->failed() || $response['aud'] !== env('GOOGLE_CLIENT_ID')) {
            return response()->json([
                'success' => false,
                'message' => 'Token tidak valid'
            ], 401);
        }

        $googleData = $response->json();

        $user = User::where('email', $googleData['email'])->first();

        if ($user && $user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Account is ' . $user->status
            ], 403);
        }

        if (!$user) {
            $existingRequest = \App\Models\UserRequest::where('email', $googleData['email'])->first();

            if ($existingRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akun sedang menunggu persetujuan admin.',
                ], 403);
            }

            \App\Models\UserRequest::create([
                'name'             => $googleData['name'] ?? $googleData['email'],
                'email'            => $googleData['email'],
                'phone'            => null,
                'password'         => Hash::make(\Illuminate\Support\Str::random(16)),
                'requested_role'   => 'customer',
                'status_requested' => 'pending',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Akun sedang menunggu persetujuan admin.',
            ], 403);
        }

        $user->tokens()->delete();

        $accessToken  = $user->createAccessToken('google_login', ['*']);
        $refreshToken = $user->createRefreshToken('google_login');

        return response()->json([
            'success'       => true,
            'access_token'  => $accessToken->plainTextToken,
            'refresh_token' => $refreshToken->plainTextToken,
            'user'          => new UserResource($user)
        ]);
    }

    // AUTH CUSTOMER (REGISTER & LOGIN)
    public function registerCustomer(Request $request)
    {
        try {
            $validated = $request->validate([
                'name'     => 'required|string|max:255',
                'email'    => [
                    'required', 'email',
                    Rule::unique('users', 'email'),
                    Rule::unique('user_requests', 'email'),
                ],
                'phone'    => [
                    'nullable',
                    Rule::unique('users', 'phone'),
                    Rule::unique('user_requests', 'phone'),
                ],
                'password' => 'required|string|min:8|confirmed',
            ]);

            $existingRequest = UserRequest::where('email', $validated['email'])->first();

            if ($existingRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda sudah pernah mendaftar dan sedang menunggu persetujuan admin.',
                ], 403);
            }

            UserRequest::create([
                'name'             => $validated['name'],
                'email'            => $validated['email'],
                'phone'            => $validated['phone'] ?? null,
                'password'         => Hash::make($validated['password']),
                'requested_role'   => 'customer',
                'status_requested' => 'pending',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Registrasi customer berhasil, menunggu persetujuan admin',
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors'  => $e->errors(),
            ], 422);
        }
    }

    public function loginCustomer(Request $request)
    {
        try {
            $request->validate([
                'email'    => 'required|email',
                'password' => 'required|string',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors'  => $e->errors(),
            ], 422);
        }

        try {
            $key = 'login:' . $request->ip() . ':' . $request->email;

            if (RateLimiter::tooManyAttempts($key, 7)) {
                $seconds = RateLimiter::availableIn($key);
                return response()->json([
                    'success' => false,
                    'message' => "Terlalu banyak percobaan. Coba lagi dalam {$seconds} detik.",
                ], 429);
            }

            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                RateLimiter::hit($key, 60);
                return response()->json([
                    'success' => false,
                    'message' => 'Email atau password salah',
                ], 401);
            }

            RateLimiter::clear($key);

            if (!in_array($user->role, ['customer', 'admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akun tidak memiliki akses',
                ], 403);
            }

            $maintenance = SystemSetting::where('key', 'maintenance_mode')->value('value');

            if ($maintenance == '1' && $user->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Sistem sedang maintenance. Anda tidak bisa login.',
                ], 503);
            }

            if ($user->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Akun tidak aktif',
                ], 403);
            }

            $user->tokens()->delete();

            $accessToken  = $user->createAccessToken('customer_app', ['*']);
            $refreshToken = $user->createRefreshToken('customer_app');

            $ip        = $request->ip();
            $userAgent = $request->userAgent();

            $isNewDevice = !LoginActivity::where('user_id', $user->id)
                ->where('ip_address', $ip)
                ->where('status', 'success')
                ->exists();

            LoginActivity::create([
                'user_id'    => $user->id,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'location'   => $this->getLocationFromIp($ip),
                'status'     => 'success',
            ]);

            if ($isNewDevice) {
                $user->notify(new NewDeviceLoginNotification($ip, $userAgent));
            }

            return response()->json([
                'success' => true,
                'message' => 'Login success',
                'data'    => [
                    'user'          => new UserResource($user),
                    'access_token'  => $accessToken->plainTextToken,
                    'refresh_token' => $refreshToken->plainTextToken,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Login gagal. ' . $e->getMessage(),
            ], 500);
        }
    }

    // AUTH TRAVELER (REGISTER & LOGIN)
    public function registerTraveler(Request $request)
    {
        try {
            $validated = $request->validate([
                'name'            => 'required|string|max:255',
                'email'           => [
                    'required', 'email',
                    Rule::unique('travelers', 'email'),
                    Rule::unique('traveler_requests', 'email'),
                ],
                'phone'           => [
                    'required',
                    'string',
                    Rule::unique('travelers', 'phone'),
                    Rule::unique('traveler_requests', 'phone'),
                ],
                'password'        => 'required|string|min:8|confirmed',
                'city'            => 'required|string|max:255',
                'province'        => 'required|string|max:255',
                'address'         => 'required|string',
                'birth_date'      => 'required|date',
                'gender'          => 'required|in:male,female',
                'ktp_number'      => 'required|string|max:20',
                'ktp_photo'       => 'required|file|image|max:2048',
                'selfie_with_ktp' => 'required|file|image|max:2048',
                'pass_photo'      => 'required|file|image|max:2048',
                'sim_card_photo'  => 'required|file|image|max:2048',
            ]);

            $existingRequest = TravelerRequest::where('email', $validated['email'])->first();

            if ($existingRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda sudah pernah mendaftar dan sedang menunggu persetujuan admin.',
                ], 403);
            }

            $photos = ['ktp_photo', 'selfie_with_ktp', 'pass_photo', 'sim_card_photo'];
            foreach ($photos as $photo) {
                if ($request->hasFile($photo)) {
                    $validated[$photo] = $request->file($photo)->store("traveler/{$photo}", 'public');
                }
            }

            TravelerRequest::create([
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

            return response()->json([
                'success' => true,
                'message' => 'Registrasi traveler berhasil, menunggu persetujuan admin',
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors'  => $e->errors(),
            ], 422);
        }
    }

    public function loginTraveler(Request $request)
    {
        try {
            $request->validate([
                'email'    => 'required|email',
                'password' => 'required|string',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors'  => $e->errors(),
            ], 422);
        }

        try {
            $key = 'login_traveler:' . $request->ip() . ':' . $request->email;

            if (RateLimiter::tooManyAttempts($key, 7)) {
                $seconds = RateLimiter::availableIn($key);
                return response()->json([
                    'success' => false,
                    'message' => "Terlalu banyak percobaan. Coba lagi dalam {$seconds} detik.",
                ], 429);
            }

            $traveler = Traveler::where('email', $request->email)->first();

            if (!$traveler || !Hash::check($request->password, $traveler->password)) {
                RateLimiter::hit($key, 60);
                return response()->json([
                    'success' => false,
                    'message' => 'Email atau password salah',
                ], 401);
            }

            RateLimiter::clear($key);

            $maintenance = SystemSetting::where('key', 'maintenance_mode')->value('value');

            if ($maintenance == '1') {
                return response()->json([
                    'success' => false,
                    'message' => 'Sistem sedang maintenance. Anda tidak bisa login.',
                ], 503);
            }

            if ($traveler->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Akun tidak aktif',
                ], 403);
            }

            $traveler->tokens()->delete();

            $accessToken  = $traveler->createAccessToken('traveler_app', ['*']);
            $refreshToken = $traveler->createRefreshToken('traveler_app');

            return response()->json([
                'success' => true,
                'message' => 'Login success',
                'data'    => [
                    'traveler'      => new TravelerResource($traveler),
                    'access_token'  => $accessToken->plainTextToken,
                    'refresh_token' => $refreshToken->plainTextToken,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Login gagal. ' . $e->getMessage(),
            ], 500);
        }
    }

    // UNIFIED LOGIN (Customer + Traveler)
    public function login(Request $request)
    {
        try {
            $request->validate([
                'email'    => 'required|email',
                'password' => 'required|string',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors'  => $e->errors(),
            ], 422);
        }

        try {
            $key = 'login:' . $request->ip() . ':' . $request->email;

            if (RateLimiter::tooManyAttempts($key, 7)) {
                $seconds = RateLimiter::availableIn($key);
                return response()->json([
                    'success' => false,
                    'message' => "Terlalu banyak percobaan. Coba lagi dalam {$seconds} detik.",
                ], 429);
            }

            // Cek di tabel users dulu
            $user = User::where('email', $request->email)->first();
            $traveler = null;

            // Kalau tidak ada di users, cek di tabel travelers
            if (!$user) {
                $traveler = Traveler::where('email', $request->email)->first();
            }

            $account = $user ?? $traveler;

            // Email tidak ditemukan atau password salah
            if (!$account || !Hash::check($request->password, $account->password)) {
                RateLimiter::hit($key, 60);
                return response()->json([
                    'success' => false,
                    'message' => 'Email atau password salah',
                ], 401);
            }

            RateLimiter::clear($key);

            // Cek maintenance mode
            $maintenance = SystemSetting::where('key', 'maintenance_mode')->value('value');
            if ($maintenance == '1') {
                if (!($user && $user->role === 'admin')) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Sistem sedang maintenance. Anda tidak bisa login.',
                    ], 503);
                }
            }

            // Cek status akun
            if ($account->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Akun tidak aktif',
                ], 403);
            }

            // Cek role user (customer/admin saja)
            if ($user && !in_array($user->role, ['customer', 'admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akun tidak memiliki akses',
                ], 403);
            }

            // Buat token
            $account->tokens()->delete();
            $tokenName    = $traveler ? 'traveler_app' : 'customer_app';
            $accessToken  = $account->createAccessToken($tokenName, ['*']);
            $refreshToken = $account->createRefreshToken($tokenName);

            // Login activity (hanya untuk user)
            if ($user) {
                $ip        = $request->ip();
                $userAgent = $request->userAgent();

                $isNewDevice = !LoginActivity::where('user_id', $user->id)
                    ->where('ip_address', $ip)
                    ->where('status', 'success')
                    ->exists();

                LoginActivity::create([
                    'user_id'    => $user->id,
                    'ip_address' => $ip,
                    'user_agent' => $userAgent,
                    'location'   => $this->getLocationFromIp($ip),
                    'status'     => 'success',
                ]);

                if ($isNewDevice) {
                    $user->notify(new NewDeviceLoginNotification($ip, $userAgent));
                }
            }

            // Response
            $isTraveler = $traveler !== null;
            $role       = $isTraveler ? 'traveler' : $account->role;
            $resource   = $isTraveler
                ? new TravelerResource($account)
                : new UserResource($account);

            return response()->json([
                'success' => true,
                'message' => 'Login success',
                'data'    => [
                    'user'          => $resource,
                    'role'          => $role,
                    'access_token'  => $accessToken->plainTextToken,
                    'refresh_token' => $refreshToken->plainTextToken,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Login gagal. ' . $e->getMessage(),
            ], 500);
        }
    }

    // Forgot Password
    public function forgotPassword(Request $request)
    {
        $key = 'forgot_password:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 7)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'success' => false,
                'message' => "Terlalu banyak percobaan. Coba lagi dalam {$seconds} detik.",
            ], 429);
        }

        RateLimiter::hit($key, 60);

        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => true,
                'message' => 'Jika email terdaftar, link reset akan dikirim.'
            ]);
        }

        $token = \Illuminate\Support\Str::random(64);
        Cache::put('reset_password_' . $token, $user->id, now()->addMinutes(30));
        Mail::to($user->email)->send(new ResetPasswordMail($token));

        return response()->json([
            'success' => true,
            'message' => 'Jika email terdaftar, link reset akan dikirim.'
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token'    => 'required',
            'password' => 'required|min:8|confirmed'
        ]);

        $userId = Cache::get('reset_password_' . $request->token);

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'Token tidak valid atau expired'
            ], 400);
        }

        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan'
            ], 404);
        }

        $user->update([
            'password' => Hash::make($request->password)
        ]);

        Cache::forget('reset_password_' . $request->token);

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil direset'
        ]);
    }

    // LOGOUT
    public function logout(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->currentAccessToken()) {
            return response()->json([
                'success' => false,
                'message' => 'User belum login',
            ], 401);
        }

        $user->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout success',
        ]);
    }

    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout dari semua device berhasil',
        ]);
    }

    // PROFILE
    public function me(Request $request)
    {
        $user = $request->user();

        $resource = $user instanceof Traveler
            ? new TravelerResource($user)
            : new UserResource($user);

        return response()->json([
            'success' => true,
            'data'    => $resource,
        ]);
    }

    // REFRESH TOKEN
    public function refreshToken(Request $request)
    {
        $request->validate([
            'refresh_token' => 'required|string',
        ]);

        $token = PersonalAccessToken::findToken($request->refresh_token);

        if (!$token || !in_array('refresh', $token->abilities)) {
            return response()->json([
                'success' => false,
                'message' => 'Refresh token tidak valid',
            ], 401);
        }

        if ($token->expires_at && $token->expires_at->isPast()) {
            $token->delete();
            return response()->json([
                'success' => false,
                'message' => 'Refresh token expired',
            ], 401);
        }

        $user = $token->tokenable;
        $token->delete();

        $accessToken     = $user->createAccessToken('api', ['*']);
        $newRefreshToken = $user->createRefreshToken('api');

        return response()->json([
            'success'       => true,
            'access_token'  => $accessToken->plainTextToken,
            'refresh_token' => $newRefreshToken->plainTextToken,
        ]);
    }

    // HELPER
    private function getLocationFromIp(string $ip): string
    {
        try {
            $location = \Stevebauman\Location\Facades\Location::get($ip);
            return $location
                ? "{$location->cityName}, {$location->regionName}, {$location->countryName}"
                : 'Unknown';
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }
}