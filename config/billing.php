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
            'features' => [
                'email_alerts' => true,
                'slack_integration' => false,
                'webhooks' => false,
                'team_invitations' => false,
                'add_ons' => false,
            ],
        ],
        'pro' => [
            'name' => 'Pro',
            'price' => 9,
            'price_ids' => array_filter(explode(',', env('PADDLE_PRICE_IDS_PRO', ''))),
            'monitors' => 5,
            'users' => 1,
            'check_interval' => '5-minute',
            'features' => [
                'email_alerts' => true,
                'slack_integration' => false,
                'webhooks' => false,
                'team_invitations' => false,
                'add_ons' => true,
            ],
        ],
        'team' => [
            'name' => 'Team',
            'price' => 29,
            'price_ids' => array_filter(explode(',', env('PADDLE_PRICE_IDS_TEAM', ''))),
            'monitors' => 20,
            'users' => 5,
            'check_interval' => '5-minute',
            'features' => [
                'email_alerts' => true,
                'slack_integration' => true,
                'webhooks' => true,
                'team_invitations' => true,
                'add_ons' => true,
            ],
        ],
    ],

    'addons' => [
        'extra_monitor_pack' => [
            'name' => 'Extra Monitor Pack',
            'price' => 5,
            'price_ids' => array_filter(explode(',', env('PADDLE_PRICE_IDS_ADDON_MONITOR_PACK', ''))),
            'pack_size' => 5,
            'allowed_plans' => ['pro', 'team'],
        ],
        'extra_seat_pack' => [
            'name' => 'Extra Team Member Pack',
            'price' => 6,
            'price_ids' => array_filter(explode(',', env('PADDLE_PRICE_IDS_ADDON_SEAT_PACK', ''))),
            'pack_size' => 3,
            'allowed_plans' => ['team'],
        ],
        'interval_override_2' => [
            'name' => '2-Minute Checks',
            'price' => 15,
            'price_ids' => array_filter(explode(',', env('PADDLE_PRICE_IDS_ADDON_INTERVAL_2', ''))),
            'allowed_plans' => ['pro', 'team'],
        ],
        'interval_override_3' => [
            'name' => '3-Minute Checks',
            'price' => 9,
            'price_ids' => array_filter(explode(',', env('PADDLE_PRICE_IDS_ADDON_INTERVAL_3', ''))),
            'allowed_plans' => ['pro', 'team'],
        ],
        'interval_override_4' => [
            'name' => '4-Minute Checks',
            'price' => 5,
            'price_ids' => array_filter(explode(',', env('PADDLE_PRICE_IDS_ADDON_INTERVAL_4', ''))),
            'allowed_plans' => ['pro', 'team'],
        ],
    ],
];