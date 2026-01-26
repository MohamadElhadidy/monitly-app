<?php

return [
    'paddle_webhook_secret' => env('PADDLE_WEBHOOK_SECRET'),

    'grace_days' => (int) env('BILLING_GRACE_DAYS', 7),

    'plans' => [
        'pro' => [
            'price_ids' => array_filter(array_map('trim', explode(',', (string) env('PADDLE_PRICE_IDS_PRO', '')))),
        ],
        'team' => [
            'price_ids' => array_filter(array_map('trim', explode(',', (string) env('PADDLE_PRICE_IDS_TEAM', '')))),
        ],
    ],

    'addons' => [
        'extra_monitor_pack' => [
            'price_ids' => array_filter(array_map('trim', explode(',', (string) env('PADDLE_PRICE_IDS_ADDON_MONITOR_PACK', '')))),
            'pack_size' => 5,
        ],

        'extra_seat_pack' => [
            'price_ids' => array_filter(array_map('trim', explode(',', (string) env('PADDLE_PRICE_IDS_ADDON_SEAT_PACK', '')))),
            'pack_size' => 3,
        ],

        'interval_override_2' => [
            'price_ids' => array_filter(array_map('trim', explode(',', (string) env('PADDLE_PRICE_IDS_ADDON_INTERVAL_2', '')))),
            'minutes' => 2,
        ],
        'interval_override_1' => [
            'price_ids' => array_filter(array_map('trim', explode(',', (string) env('PADDLE_PRICE_IDS_ADDON_INTERVAL_1', '')))),
            'minutes' => 1,
        ],
    ],

    /**
     * Revenue estimate inputs (optional). These are NOT authoritative accounting numbers.
     * Set them in .env to approximate MRR for the admin dashboard.
     */
    'estimate_prices' => [
        'pro_monthly' => (float) env('ESTIMATE_PRO_MONTHLY', 0),
        'team_monthly' => (float) env('ESTIMATE_TEAM_MONTHLY', 0),

        'addon_monitor_pack_monthly' => (float) env('ESTIMATE_ADDON_MONITOR_PACK_MONTHLY', 0),
        'addon_seat_pack_monthly' => (float) env('ESTIMATE_ADDON_SEAT_PACK_MONTHLY', 0),

        // Charged when interval override is enabled (per account/team). If you charge differently, set to 0.
        'addon_interval_2_monthly' => (float) env('ESTIMATE_ADDON_INTERVAL_2_MONTHLY', 0),
        'addon_interval_1_monthly' => (float) env('ESTIMATE_ADDON_INTERVAL_1_MONTHLY', 0),
    ],
];