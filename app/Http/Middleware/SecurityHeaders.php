<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $next($request);

        // Basic hardening headers (safe defaults; CSP kept minimal to avoid breaking Livewire/Vite).
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // If you embed nothing, keep frame-ancestors 'none'
        $csp = "default-src 'self'; "
             . "base-uri 'self'; "
             . "object-src 'none'; "
             . "frame-ancestors 'none'; "
             . "form-action 'self'; "
             . "img-src 'self' data: https:; "
             . "font-src 'self' data: https:; "
             . "style-src 'self' 'unsafe-inline' https:; "
             . "script-src 'self' 'unsafe-inline' 'unsafe-eval' https:; "
             . "connect-src 'self' https: wss:;";

        // Only set CSP for web pages (skip for downloads/binary)
        $contentType = (string) $response->headers->get('Content-Type', '');
        if (str_contains($contentType, 'text/html')) {
            $response->headers->set('Content-Security-Policy', $csp);
        }

        // HSTS only when running over HTTPS and in production
        if (app()->environment('production') && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        // Reasonable permissions policy baseline
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=()'
        );

        return $response;
    }
}