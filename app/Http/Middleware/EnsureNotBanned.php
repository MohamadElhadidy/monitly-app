<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotBanned
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->banned_at) {
            auth()->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            abort(403, 'Your account has been banned.');
        }

        return $next($request);
    }
}