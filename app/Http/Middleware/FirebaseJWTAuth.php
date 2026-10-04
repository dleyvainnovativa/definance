<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Kreait\Firebase\Contract\Auth as FirebaseAuth;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;
use Kreait\Firebase\Exception\Auth\RevokedIdToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies a Firebase ID token (Bearer) on API requests and binds the local
 * user to the request AND the auth guard, so $request->user(), Auth::id() and
 * the global UserScope all see the same tenant.
 */
class FirebaseJWTAuth
{
    public function __construct(private readonly FirebaseAuth $auth)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['message' => 'Authentication token not provided.'], 401);
        }

        try {
            $verified = $this->auth->verifyIdToken($token, true);
        } catch (RevokedIdToken) {
            return response()->json(['message' => 'Token has been revoked.'], 401);
        } catch (FailedToVerifyToken $e) {
            return response()->json(['message' => 'Invalid token: '.$e->getMessage()], 401);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'Could not verify token.'], 401);
        }

        $firebaseUid = $verified->claims()->get('sub');

        $user = User::where('firebase_uid', $firebaseUid)->first();

        if (! $user) {
            return response()->json(['message' => 'User not found.'], 401);
        }

        // Bind to both the request resolver and the auth guard so Auth::id()
        // (used by the global UserScope) resolves to this user for the request.
        $request->setUserResolver(fn () => $user);
        Auth::setUser($user);
        $request->attributes->set('firebase_uid', $firebaseUid);

        return $next($request);
    }
}
