<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security response headers for every request.
 *
 * A full Content-Security-Policy is intentionally left commented: it has to
 * allow Vite's bundle, Google Fonts and the Firebase endpoints, so enable it
 * once those origins are confirmed in production (see the deploy runbook).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Example CSP — verify the Firebase + Google Fonts origins for your project, then enable:
        // $response->headers->set('Content-Security-Policy',
        //     "default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
        //     ."font-src https://fonts.gstatic.com; connect-src 'self' https://*.googleapis.com https://*.firebaseio.com; "
        //     ."script-src 'self'; frame-src https://*.firebaseapp.com");

        return $response;
    }
}
