<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class MultiAuthSanctum
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $accessToken = PersonalAccessToken::findToken($token);

        if (!$accessToken) {
            return response()->json([
                'success' => false,
                'message' => 'Token tidak valid',
            ], 401);
        }

        // Check Token
        if ($accessToken->expires_at && $accessToken->expires_at->isPast()) {
            $accessToken->delete();
            return response()->json([
                'success' => false,
                'message' => 'Token expired',
            ], 401);
        }

        // Check Refresh token
        if (in_array('refresh', $accessToken->abilities)) {
            return response()->json([
                'success' => false,
                'message' => 'Gunakan access token, bukan refresh token',
            ], 401);
        }

        $user = $accessToken->tokenable;

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan',
            ], 401);
        }
        
        $request->setUserResolver(fn () => $user);

        // Update last_used_at
        $accessToken->forceFill(['last_used_at' => now()])->save();

        return $next($request);
    }
}