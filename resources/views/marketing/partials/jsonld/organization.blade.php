@php
    $org = config('seo.organization');
    $description = $locale === 'ar' ? ($org['description_ar'] ?? '') : ($org['description_en'] ?? '');
    $sameAs = array_values(array_filter([
        config('marketing.social.twitter'),
        config('marketing.social.linkedin'),
        config('marketing.social.facebook'),
        config('marketing.social.instagram'),
        config('marketing.social.youtube'),
    ]));
    $logo = url($org['logo'] ?? '/assets/company/logo.png');
    $payload = [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => $org['name'],
        'legalName' => $org['legal_name'],
        'url' => config('seo.site_url'),
        'logo' => $logo,
        'description' => $description,
        'foundingDate' => $org['founding_date'] ?? null,
        'address' => [
            '@type' => 'PostalAddress',
            'addressCountry' => $org['address_country'] ?? 'AE',
        ],
        'contactPoint' => [
            '@type' => 'ContactPoint',
            'email' => config('marketing.company.email'),
            'contactType' => 'sales',
            'availableLanguage' => ['en', 'ar'],
        ],
    ];
    if (!empty($sameAs)) {
        $payload['sameAs'] = $sameAs;
    }
    $payload = array_filter($payload, fn ($v) => $v !== null && $v !== '');
@endphp
<script type="application/ld+json">{!! json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
