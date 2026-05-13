<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Marketing site identity
    |--------------------------------------------------------------------------
    */
    'company' => [
        'name'        => 'Seven S Capital',
        'product'     => 'Seven S Automotive',
        'legal_name'  => 'Seven S Capital',
        'email'       => env('MARKETING_CONTACT_EMAIL', 'info@seven-scapital.com'),
        'sales_email' => env('MARKETING_SALES_EMAIL', 'sales@seven-scapital.com'),
        'phone'       => env('MARKETING_PHONE', ''),
        'whatsapp'    => env('MARKETING_WHATSAPP', ''),
        'address'     => env('MARKETING_ADDRESS', 'United Arab Emirates'),
    ],

    'social' => [
        'twitter'   => env('MARKETING_SOCIAL_TWITTER', ''),
        'linkedin'  => env('MARKETING_SOCIAL_LINKEDIN', ''),
        'facebook'  => env('MARKETING_SOCIAL_FACEBOOK', ''),
        'instagram' => env('MARKETING_SOCIAL_INSTAGRAM', ''),
        'youtube'   => env('MARKETING_SOCIAL_YOUTUBE', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pricing plans (placeholders — wire to billing config later)
    |--------------------------------------------------------------------------
    | currency, price are placeholders; the page reads from this config so real
    | prices can be moved to DB/Stripe without changing the view.
    */
    'pricing' => [
        'currency'     => 'AED',
        'period_label' => 'monthly',
        'plans' => [
            [
                'key'           => 'starter',
                'price'         => 99,
                'highlight'     => false,
                'cta'           => 'start_trial',
                'feature_keys'  => ['1_branch', 'basic_users', 'job_cards', 'invoices', 'customer_records', 'basic_reports'],
            ],
            [
                'key'           => 'professional',
                'price'         => 249,
                'highlight'     => true,
                'cta'           => 'start_trial',
                'feature_keys'  => ['multi_users', 'inventory_basics', 'customer_history', 'advanced_reports', 'payments', 'multilingual_ui'],
            ],
            [
                'key'           => 'business',
                'price'         => 499,
                'highlight'     => false,
                'cta'           => 'start_trial',
                'feature_keys'  => ['workshop_full', 'spare_parts', 'accounting', 'multi_branch', 'supplier_management', 'advanced_reports'],
            ],
            [
                'key'           => 'enterprise',
                'price'         => null,
                'highlight'     => false,
                'cta'           => 'contact_sales',
                'feature_keys'  => ['custom_branches', 'advanced_permissions', 'dedicated_onboarding', 'custom_support', 'future_integrations'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Lead form options (used by Book Demo, Start Trial, Contact)
    |--------------------------------------------------------------------------
    */
    'business_types' => [
        'auto_repair_workshop',
        'car_service_center',
        'spare_parts_business',
        'other_automotive',
    ],

    'interested_systems' => [
        'workshop_management',
        'spare_parts_inventory',
        'automotive_accounting',
        'full_suite',
    ],

    'preferred_languages' => [
        'en',
        'ar',
    ],

    'countries' => [
        'AE', 'SA', 'KW', 'QA', 'BH', 'OM', 'JO', 'EG', 'LB', 'IQ', 'MA', 'TN', 'DZ', 'LY', 'YE', 'PS', 'SD', 'OTHER',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sitemap entries (canonical paths after the locale prefix)
    |--------------------------------------------------------------------------
    | Each entry is rendered for both /en/<path> and /ar/<path>.
    */
    'sitemap_paths' => [
        ['route' => 'marketing.home',                       'priority' => '1.0', 'changefreq' => 'weekly'],
        ['route' => 'marketing.products.index',             'priority' => '0.9', 'changefreq' => 'monthly'],
        ['route' => 'marketing.products.workshop',          'priority' => '0.9', 'changefreq' => 'monthly'],
        ['route' => 'marketing.products.spare-parts',       'priority' => '0.9', 'changefreq' => 'monthly'],
        ['route' => 'marketing.products.accounting',        'priority' => '0.9', 'changefreq' => 'monthly'],
        ['route' => 'marketing.pricing',                    'priority' => '0.9', 'changefreq' => 'monthly'],
        ['route' => 'marketing.book-demo',                  'priority' => '0.8', 'changefreq' => 'monthly'],
        ['route' => 'marketing.start-trial',                'priority' => '0.8', 'changefreq' => 'monthly'],
        ['route' => 'marketing.contact',                    'priority' => '0.8', 'changefreq' => 'monthly'],
        ['route' => 'marketing.security',                   'priority' => '0.5', 'changefreq' => 'yearly'],
        ['route' => 'marketing.privacy',                    'priority' => '0.4', 'changefreq' => 'yearly'],
        ['route' => 'marketing.terms',                      'priority' => '0.4', 'changefreq' => 'yearly'],
    ],
];
