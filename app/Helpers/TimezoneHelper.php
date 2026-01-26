<?php

namespace App\Helpers;

use Carbon\Carbon;

class TimezoneHelper
{
    /**
     * Format a date/time in the user's timezone
     *
     * @param mixed $date
     * @param string $format
     * @param string|null $timezone
     * @return string
     */
    public static function format($date, string $format = 'Y-m-d H:i:s', ?string $timezone = null): string
    {
        if (!$date) {
            return '—';
        }

        $timezone = $timezone ?? auth()->user()?->timezone ?? config('app.timezone', 'UTC');
        
        try {
            $carbon = Carbon::parse($date);
            if ($timezone) {
                $carbon = $carbon->setTimezone($timezone);
            }
            return $carbon->format($format);
        } catch (\Exception $e) {
            return '—';
        }
    }

    /**
     * Get a human-readable relative time in the user's timezone
     *
     * @param mixed $date
     * @param string|null $timezone
     * @return string
     */
    public static function diffForHumans($date, ?string $timezone = null): string
    {
        if (!$date) {
            return '—';
        }

        $timezone = $timezone ?? auth()->user()?->timezone ?? config('app.timezone', 'UTC');
        
        try {
            $carbon = Carbon::parse($date);
            if ($timezone) {
                $carbon = $carbon->setTimezone($timezone);
            }
            return $carbon->diffForHumans();
        } catch (\Exception $e) {
            return '—';
        }
    }
}
