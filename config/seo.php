<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Canonical site URL
    |--------------------------------------------------------------------------
    */
    'site_url'    => env('MARKETING_SITE_URL', 'https://seven-scapital.com'),
    'default_locale' => 'en',
    'supported_locales' => ['en', 'ar'],

    /*
    |--------------------------------------------------------------------------
    | Default SEO defaults (overridden per page via $seo array)
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'title_suffix'    => 'Seven S Automotive',
        'og_image'        => '/assets/marketing/og/default.jpg',
        'twitter_card'    => 'summary_large_image',
        'twitter_handle'  => env('MARKETING_TWITTER_HANDLE', ''),
        'theme_color'     => '#0d6efd',
    ],

    /*
    |--------------------------------------------------------------------------
    | Organization data for JSON-LD
    |--------------------------------------------------------------------------
    */
    'organization' => [
        'name'            => 'Seven S Capital',
        'legal_name'      => 'Seven S Capital',
        'logo'            => '/assets/company/logo.png',
        'founding_date'   => '2024',
        'address_country' => 'AE',
        'description_en'  => 'Seven S Capital builds cloud business systems for automotive workshops, service centers, and spare parts businesses across the Gulf and Middle East.',
        'description_ar'  => 'Seven S Capital تبني أنظمة أعمال سحابية لورش السيارات ومراكز الصيانة وتجار قطع الغيار في الخليج والشرق الأوسط.',
    ],
];
