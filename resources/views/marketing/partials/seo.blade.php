@php
    /** @var array $seo */
    /** @var string $locale */
    $titleSuffix  = config('seo.defaults.title_suffix', 'Seven S Automotive');
    $title        = trim((string) ($seo['title'] ?? ''));
    $fullTitle    = $title === ''
        ? $titleSuffix
        : $title . ' | ' . $titleSuffix;
    $description  = (string) ($seo['description'] ?? config('seo.organization.description_' . $locale, ''));
    $canonical    = (string) ($seo['canonical'] ?? url()->current());
    $routeName    = (string) ($seo['route_name'] ?? '');
    $routeParams  = (array) ($seo['route_params'] ?? []);
    $ogImage      = (string) ($seo['og_image'] ?? asset(config('seo.defaults.og_image')));
    $ogType       = (string) ($seo['og_type'] ?? 'website');
    $twitterCard  = (string) ($seo['twitter_card'] ?? config('seo.defaults.twitter_card', 'summary_large_image'));
    $twitterHandle = (string) (config('seo.defaults.twitter_handle', ''));
    $noIndex      = (bool) ($seo['no_index'] ?? false);
    $supportedLocales = config('seo.supported_locales', ['en', 'ar']);
    $defaultLocale    = config('seo.default_locale', 'en');

    $alternates = [];
    if ($routeName !== '') {
        foreach ($supportedLocales as $altLocale) {
            $alternates[$altLocale] = route($routeName, array_merge(['locale' => $altLocale], $routeParams));
        }
    }
@endphp

<title>{{ $fullTitle }}</title>
<meta name="description" content="{{ $description }}">
@if($noIndex)
    <meta name="robots" content="noindex, nofollow">
@else
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
@endif

<link rel="canonical" href="{{ $canonical }}">
@foreach($alternates as $altLocale => $altUrl)
    <link rel="alternate" hreflang="{{ $altLocale }}" href="{{ $altUrl }}">
@endforeach
@if(!empty($alternates[$defaultLocale]))
    <link rel="alternate" hreflang="x-default" href="{{ $alternates[$defaultLocale] }}">
@endif

{{-- Favicons --}}
<link rel="icon" type="image/png" href="{{ asset('assets/company/logo.png') }}">
<link rel="apple-touch-icon" href="{{ asset('assets/company/logo.png') }}">

{{-- Open Graph --}}
<meta property="og:site_name" content="{{ config('marketing.company.product', 'Seven S Automotive') }}">
<meta property="og:type" content="{{ $ogType }}">
<meta property="og:title" content="{{ $fullTitle }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:locale" content="{{ $locale === 'ar' ? 'ar_AE' : 'en_US' }}">
@foreach($supportedLocales as $altLocale)
    @if($altLocale !== $locale)
        <meta property="og:locale:alternate" content="{{ $altLocale === 'ar' ? 'ar_AE' : 'en_US' }}">
    @endif
@endforeach
<meta property="og:image" content="{{ $ogImage }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">

{{-- Twitter / X --}}
<meta name="twitter:card" content="{{ $twitterCard }}">
<meta name="twitter:title" content="{{ $fullTitle }}">
<meta name="twitter:description" content="{{ $description }}">
<meta name="twitter:image" content="{{ $ogImage }}">
@if($twitterHandle !== '')
    <meta name="twitter:site" content="{{ $twitterHandle }}">
@endif
