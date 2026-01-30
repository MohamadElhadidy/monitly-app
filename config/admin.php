<?php

return [
    'owner_email' => env('MONITLY_OWNER_EMAIL', ''),

    'queue_names' => [
        'checks_standard',
        'checks_priority',
        'incidents',
        'notifications',
        'webhooks_in',
        'webhooks_out',
        'sla',
        'maintenance',
        'exports',
    ],
];
