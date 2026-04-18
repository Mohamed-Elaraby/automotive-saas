<?php

return [

    'default_gateway' => env('BILLING_DEFAULT_GATEWAY', 'stripe'),

    'grace_period_days' => env('BILLING_GRACE_PERIOD_DAYS', 3),

    'portal_return_url' => env('BILLING_PORTAL_RETURN_URL', env('APP_URL') . '/workspace/admin/billing'),

    'gateways' => [
        'null' => [
            'driver' => 'null',
            'label' => 'Null Gateway',
        ],

        'stripe' => [
            'driver' => 'stripe',
            'label' => 'Stripe',
            'key' => env('STRIPE_KEY'),
            'secret' => env('STRIPE_SECRET'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        ],
    ],

];
