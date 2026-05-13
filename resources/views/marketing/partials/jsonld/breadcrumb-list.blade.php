@php
    $crumbs = $breadcrumbs ?? null;
    if (!is_array($crumbs) || empty($crumbs)) { return; }

    $items = [];
    $position = 1;
    foreach ($crumbs as $crumb) {
        $entry = [
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => $crumb['title'] ?? '',
        ];
        if (!empty($crumb['url'])) {
            $entry['item'] = $crumb['url'];
        }
        $items[] = $entry;
    }

    $payload = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $items,
    ];
@endphp
<script type="application/ld+json">{!! json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
