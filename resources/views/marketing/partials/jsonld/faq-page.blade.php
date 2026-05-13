@php
    /** @var array $seo */
    $pageKey = $seo['page_key'] ?? null;
    if (!$pageKey) { return; }

    $faqs = trans("marketing.{$pageKey}.faq");
    if (!is_array($faqs) || empty($faqs)) { return; }

    $entities = [];
    foreach ($faqs as $faq) {
        $entities[] = [
            '@type' => 'Question',
            'name' => $faq['q'] ?? '',
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $faq['a'] ?? '',
            ],
        ];
    }

    $payload = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => $entities,
    ];
@endphp
<script type="application/ld+json">{!! json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
