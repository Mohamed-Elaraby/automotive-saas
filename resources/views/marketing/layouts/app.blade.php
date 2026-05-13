@php
    /** @var array $seo */
    /** @var string $locale */
    $locale = $locale ?? 'en';
    $isRtl  = $locale === 'ar';
    $dir    = $isRtl ? 'rtl' : 'ltr';
    $seo    = $seo ?? [];
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="theme-color" content="{{ config('seo.defaults.theme_color', '#0d6efd') }}">

    @include('marketing.partials.seo', ['seo' => $seo, 'locale' => $locale])

    {{-- Theme fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    @if($isRtl)
        <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @else
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @endif

    {{-- Bootstrap (LTR or RTL) --}}
    @if($isRtl)
        <link rel="stylesheet" href="{{ asset('theme/css/bootstrap.rtl.min.css') }}">
    @else
        <link rel="stylesheet" href="{{ asset('theme/css/bootstrap.min.css') }}">
    @endif

    {{-- Iconsax (already shipped with Kanakku) --}}
    <link rel="stylesheet" href="{{ asset('theme/css/iconsax.css') }}">

    {{-- Marketing-only stylesheet (inline so we don't depend on Vite build) --}}
    @include('marketing.partials.styles', ['isRtl' => $isRtl])

    @stack('head')
</head>
<body class="marketing-body marketing-locale-{{ $locale }}">
    <a href="#main-content" class="visually-hidden-focusable marketing-skip-link">{{ __('marketing.a11y.skip_to_content') }}</a>

    @include('marketing.partials.header', ['locale' => $locale])

    @hasSection('breadcrumbs')
        @yield('breadcrumbs')
    @elseif(!empty($breadcrumbs ?? null))
        @include('marketing.partials.breadcrumbs', ['items' => $breadcrumbs, 'locale' => $locale])
    @endif

    <main id="main-content" class="marketing-main">
        @yield('content')
    </main>

    @hasSection('cta-after')
        @yield('cta-after')
    @else
        @include('marketing.components.cta-section', ['variant' => 'global'])
    @endif

    @include('marketing.partials.footer', ['locale' => $locale])

    {{-- JSON-LD structured data --}}
    @foreach(($seo['jsonld'] ?? []) as $jsonldName)
        @includeIf('marketing.partials.jsonld.' . $jsonldName, ['locale' => $locale, 'seo' => $seo])
    @endforeach

    @stack('jsonld')

    {{-- Bootstrap JS bundle --}}
    <script src="{{ asset('theme/js/bootstrap.bundle.min.js') }}"></script>
    @include('marketing.partials.scripts')
    @stack('scripts')
</body>
</html>
