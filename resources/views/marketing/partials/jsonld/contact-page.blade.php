@php
    $payload = [
        '@context' => 'https://schema.org',
        '@type' => 'ContactPage',
        'name' => __('marketing.contact.h1'),
        'url' => route('marketing.contact', ['locale' => $locale]),
        'inLanguage' => $locale === 'ar' ? 'ar-AE' : 'en-US',
        'about' => [
            '@type' => 'Organization',
            'name' => config('marketing.company.legal_name'),
            'email' => config('marketing.company.email'),
        ],
    ];
@endphp
<script type="application/ld+json">{!! json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
