<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Session guard for Blade pages. After the Firebase login exchange
 * (AuthController@session) the user is logged into the web guard, so this
 * simply gates on Auth::check(). Kept as a thin alias; Laravel's built-in
 * 'auth' middleware is equivalent and used on the routes.
 */
class FirebaseAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}
