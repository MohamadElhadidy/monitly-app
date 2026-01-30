<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Interval policy (minutes)
    |--------------------------------------------------------------------------
    | Business rules:
    | - Free: 15
    | - Pro: 10
    | - Team: 10
    | - Business: 5
    */
    'intervals' => [
        'free' => 15,
        'pro' => 10,
        'team' => 10,
        'business' => 5,
    ],

    'allowed_intervals' => [15, 10, 5, 2, 1],

    /*
    |--------------------------------------------------------------------------
    | HTTP check settings
    |--------------------------------------------------------------------------
    */
    'http' => [
        'timeout' => 10,          // seconds (total)
        'connect_timeout' => 5,   // seconds
        'max_redirects' => 3,
        'user_agent' => 'MonitlyBot/1.0 (+https://monitly.app)',
        'max_error_message_len' => 500,
        'max_cause_len' => 255,
    ],
    
                'support' => [
            'from' => env('MONITLY_MAIL_FROM', 'notify@monitly.app'),
            'reply_to' => env('MONITLY_MAIL_REPLY_TO', 'notify@monitly.app'),
            ],
            
            
            'health' => [
            // REQUIRED in production (used by /_health). Set a long random string.
            'token' => env('HEALTHCHECK_TOKEN', ''),
            ],
            
            
            'http' => [
            'user_agent' => env('MONITLY_HTTP_USER_AGENT', 'MonitlyBot/1.0 (+https://monitly.app)'),
            'timeout_seconds' => (int) env('MONITLY_HTTP_TIMEOUT', 10),
            'connect_timeout_seconds' => (int) env('MONITLY_HTTP_CONNECT_TIMEOUT', 5),
            'max_redirects' => (int) env('MONITLY_HTTP_MAX_REDIRECTS', 3),
            ],
            
            
            'public_status' => [
            'cache_ttl_seconds' => (int) env('MONITLY_PUBLIC_STATUS_TTL', 30),
            ],
            
            
            'webhooks' => [
            'max_attempts' => (int) env('MONITLY_WEBHOOK_MAX_ATTEMPTS', 8),
            'base_backoff_seconds' => (int) env('MONITLY_WEBHOOK_BASE_BACKOFF', 10),
            ],
];
