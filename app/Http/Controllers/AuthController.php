<?php

namespace App\Http\Controllers;

use App\Models\LoginDevice;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Kreait\Firebase\Contract\Auth as FirebaseAuth;

/**
 * Bridges Firebase sign-in to a Laravel session for Blade pages.
 *
 * Flow: the browser signs in with Firebase (client SDK), obtains an ID token,
 * and POSTs it here once. We verify it server-side, upsert the local user, and
 * log them into the web (session) guard. From then on Blade routes use the
 * normal 'auth' middleware and Auth::user().
 */
class AuthController extends Controller
{
    public function session(Request $request, FirebaseAuth $firebase): JsonResponse
    {
        $request->validate(['id_token' => ['required', 'string']]);

        try {
            $verified = $firebase->verifyIdToken($request->string('id_token')->toString(), true);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Invalid or expired token.'], 401);
        }

        $claims = $verified->claims();
        $uid = $claims->get('sub');

        $user = User::firstOrNew(['firebase_uid' => $uid]);
        // Keep profile fields fresh from the identity provider.
        $user->email = $claims->get('email') ?? $user->email;
        $user->name = $claims->get('name') ?? $user->name;
        $user->save();

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        LoginDevice::create([
            'user_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'last_login_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true]);
    }
}
