<?php

return [

    'default_gateway' => env('BILLING_DEFAULT_GATEWAY', 'stripe'),

    'grace_period_days' => env('BILLING_GRACE_PERIOD_DAYS', 3),

    'gateways' => [
        'null' => [
            'driver' => 'null',
            'label' => 'Null Gateway',
        ],

        'stripe' => [
            'driver' => 'stripe',
            'label' => 'Stripe',
            'secret' => env('STRIPE_SECRET'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        ],
    ],

];
