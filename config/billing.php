<?php

return [
    'paddle_webhook_secret' => env('PADDLE_WEBHOOK_SECRET'),
    'paddle_customer_token' => env('PADDLE_CUSTOMER_TOKEN'),
    'grace_days' => (int) env('BILLING_GRACE_DAYS', 7),

    'plans' => [
        'free' => [
            'name' => 'Free',
            'price' => 0,
            'monitors' => 1,
            'users' => 1,
            'check_interval' => '15-minute',
            'history_days' => 7,
            'features' => [
                'email_alerts' => true,
                'slack_integration' => false,
                'webhooks' => false,
                'team_invitations' => false,
                'add_ons' => true,
                'full_history' => false,
            ],
        ],
        'pro' => [
            'name' => 'Pro',
            'price' => 9,
            'price_ids' => array_filter(explode(',', env('PADDLE_PRICE_IDS_PRO', ''))),
            'monitors' => 5,
            'users' => 1,
            'check_interval' => '10-minute',
            'history_days' => null, // Full history
            'features' => [
                'email_alerts' => true,
                'slack_integration' => false,
                'webhooks' => false,
                'team_invitations' => false,
                'add_ons' => true,
                'full_history' => true,
            ],
        ],
        'team' => [
            'name' => 'Team',
            'price' => 29,
            'price_ids' => array_filter(explode(',', env('PADDLE_PRICE_IDS_TEAM', ''))),
            'monitors' => 20,
            'users' => 5,
            'check_interval' => '10-minute',
            'history_days' => null, // Full history
            'features' => [
                'email_alerts' => true,
                'slack_integration' => true,
                'webhooks' => true,
                'team_invitations' => true,
                'add_ons' => true,
                'full_history' => true,
            ],
        ],
    ],

    'addons' => [
        'extra_monitor_pack' => [
            'name' => 'Extra Monitor Pack',
            'description' => '+5 monitors',
            'price' => 5,
            'price_ids' => array_filter(explode(',', env('PADDLE_PRICE_IDS_ADDON_MONITOR_PACK', ''))),
            'pack_size' => 5,
            'allowed_plans' => ['free', 'pro', 'team'],
        ],
        'faster_checks_5min' => [
            'name' => 'Faster Check Interval',
            'description' => 'Upgrade to 5-minute checks (account-wide)',
            'price' => 7,
            'price_ids' => array_filter(explode(',', env('PADDLE_PRICE_IDS_ADDON_FASTER_CHECKS', ''))),
            'allowed_plans' => ['free', 'pro', 'team'],
            'interval_minutes' => 5,
        ],
        'extra_seat_pack' => [
            'name' => 'Extra Users Pack',
            'description' => '+5 team members',
            'price' => 6,
            'price_ids' => array_filter(explode(',', env('PADDLE_PRICE_IDS_ADDON_SEAT_PACK', ''))),
            'pack_size' => 5,
            'allowed_plans' => ['team'],
        ],
    ],
];