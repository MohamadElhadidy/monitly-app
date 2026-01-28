<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetUserTimezone
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            $user = auth()->user();
            $timezone = $user->timezone ?? config('app.timezone', 'UTC');
            
            // Validate timezone
            try {
                $timezoneList = timezone_identifiers_list();
                if (in_array($timezone, $timezoneList)) {
                    config(['app.timezone' => $timezone]);
                    date_default_timezone_set($timezone);
                }
            } catch (\Exception $e) {
                // Fallback to UTC if timezone is invalid
                config(['app.timezone' => 'UTC']);
                date_default_timezone_set('UTC');
            }
        }
        
        return $next($request);
    }
}
