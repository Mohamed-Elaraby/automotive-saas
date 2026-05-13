@php
    /** @var array $seo */
    $pageKey = $seo['page_key'] ?? 'product';
    $name = __("marketing.{$pageKey}.h1");
    $description = $seo['description'] ?? '';
    $url = $seo['canonical'] ?? url()->current();

    $payload = [
        '@context' => 'https://schema.org',
        '@type' => 'SoftwareApplication',
        'name' => $name,
        'description' => $description,
        'url' => $url,
        'applicationCategory' => 'BusinessApplication',
        'operatingSystem' => 'Web',
        'inLanguage' => $locale === 'ar' ? 'ar-AE' : 'en-US',
        'offers' => [
            '@type' => 'Offer',
            'price' => '99.00',
            'priceCurrency' => 'AED',
            'url' => route('marketing.pricing', ['locale' => $locale]),
        ],
        'publisher' => [
            '@type' => 'Organization',
            'name' => config('marketing.company.legal_name'),
            'url' => config('seo.site_url'),
        ],
    ];
@endphp
<script type="application/ld+json">{!! json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
