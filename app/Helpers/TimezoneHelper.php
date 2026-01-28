<?php

namespace App\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class TimezoneHelper
{
    /**
     * Convert a UTC datetime to user's timezone
     *
     * @param Carbon|string|null $datetime
     * @param string|null $format
     * @return string
     */
    public static function toUserTimezone($datetime, ?string $format = null): string
    {
        if (is_null($datetime)) {
            return 'N/A';
        }

        $carbon = $datetime instanceof Carbon ? $datetime : Carbon::parse($datetime);
        
        // Get user's timezone
        $timezone = self::getUserTimezone();
        
        // Convert to user timezone
        $carbon->setTimezone($timezone);
        
        // Format
        if ($format) {
            return $carbon->format($format);
        }
        
        // Default format
        return $carbon->format('Y-m-d H:i:s T');
    }

    /**
     * Get human readable time (e.g., "5 minutes ago")
     *
     * @param Carbon|string|null $datetime
     * @return string
     */
    public static function diffForHumans($datetime): string
    {
        if (is_null($datetime)) {
            return 'Never';
        }

        $carbon = $datetime instanceof Carbon ? $datetime : Carbon::parse($datetime);
        
        // Set timezone for proper calculation
        $timezone = self::getUserTimezone();
        $carbon->setTimezone($timezone);
        
        return $carbon->diffForHumans();
    }

    /**
     * Get user's timezone or default to UTC
     *
     * @return string
     */
    public static function getUserTimezone(): string
    {
        if (Auth::check()) {
            $user = Auth::user();
            return $user->timezone ?? config('app.timezone', 'UTC');
        }
        
        return config('app.timezone', 'UTC');
    }

    /**
     * Convert user timezone datetime to UTC for storage
     *
     * @param string $datetime
     * @param string|null $userTimezone
     * @return Carbon
     */
    public static function toUtc(string $datetime, ?string $userTimezone = null): Carbon
    {
        $timezone = $userTimezone ?? self::getUserTimezone();
        
        return Carbon::parse($datetime, $timezone)->setTimezone('UTC');
    }

    /**
     * Get all available timezones grouped by region
     *
     * @return array
     */
    public static function getGroupedTimezones(): array
    {
        $timezones = timezone_identifiers_list();
        $grouped = [];

        foreach ($timezones as $timezone) {
            $parts = explode('/', $timezone);
            $region = $parts[0];
            
            if (!isset($grouped[$region])) {
                $grouped[$region] = [];
            }
            
            $grouped[$region][] = [
                'value' => $timezone,
                'label' => str_replace('_', ' ', $timezone),
                'offset' => self::getTimezoneOffset($timezone),
            ];
        }

        return $grouped;
    }

    /**
     * Get timezone offset string (e.g., "+05:00")
     *
     * @param string $timezone
     * @return string
     */
    public static function getTimezoneOffset(string $timezone): string
    {
        try {
            $dateTime = new \DateTime('now', new \DateTimeZone($timezone));
            $offset = $dateTime->getOffset();
            
            $hours = intdiv($offset, 3600);
            $minutes = abs(($offset % 3600) / 60);
            
            return sprintf('%+03d:%02d', $hours, $minutes);
        } catch (\Exception $e) {
            return '+00:00';
        }
    }

    /**
     * Format time with timezone indicator
     *
     * @param Carbon|string|null $datetime
     * @return string
     */
    public static function formatWithTimezone($datetime): string
    {
        if (is_null($datetime)) {
            return 'N/A';
        }

        $carbon = $datetime instanceof Carbon ? $datetime : Carbon::parse($datetime);
        $timezone = self::getUserTimezone();
        $carbon->setTimezone($timezone);
        
        return $carbon->format('M j, Y g:i A') . ' (' . $carbon->tzName . ')';
    }

    /**
     * Get short timezone name (e.g., "EST", "PST")
     *
     * @return string
     */
    public static function getShortTimezone(): string
    {
        $timezone = self::getUserTimezone();
        
        try {
            $dateTime = new \DateTime('now', new \DateTimeZone($timezone));
            return $dateTime->format('T');
        } catch (\Exception $e) {
            return 'UTC';
        }
    }
}
