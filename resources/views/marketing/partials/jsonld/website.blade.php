@php
    $payload = [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => config('marketing.company.product', 'Seven S Automotive'),
        'url' => config('seo.site_url'),
        'inLanguage' => $locale === 'ar' ? 'ar-AE' : 'en-US',
        'publisher' => [
            '@type' => 'Organization',
            'name' => config('marketing.company.legal_name'),
        ],
    ];
@endphp
<script type="application/ld+json">{!! json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
