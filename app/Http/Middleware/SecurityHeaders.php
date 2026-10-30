<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/** Strict CSP with per-request nonces, HSTS and related hardening headers (§7). */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $dev = app()->environment('local') && file_exists(public_path('hot'));
        $viteDev = $dev ? ' '.trim(file_get_contents(public_path('hot'))) : '';
        $wsDev = $dev ? ' '.preg_replace('#^http#', 'ws', trim(file_get_contents(public_path('hot')))) : '';

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-$nonce'".$viteDev,
            "style-src 'self' 'nonce-$nonce'".($dev ? " 'unsafe-inline'".$viteDev : ''),
            "img-src 'self' data: https://lh3.googleusercontent.com",
            "font-src 'self' data:".$viteDev,
            "connect-src 'self'".$viteDev.$wsDev,
            "frame-ancestors 'none'",
            "form-action 'self' https://accounts.google.com",
            "base-uri 'self'",
            "object-src 'none'",
        ]);

        $response->headers->set('Content-Security-Policy', $csp);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), interest-cohort=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        if ($request->is('v/*')) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
