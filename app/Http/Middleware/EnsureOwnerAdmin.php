<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOwnerAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $ownerEmail = (string) config('admin.owner_email');

        if (! $user || $ownerEmail === '' || $user->email !== $ownerEmail) {
            abort(403, 'Owner access only.');
        }

        return $next($request);
    }
}
