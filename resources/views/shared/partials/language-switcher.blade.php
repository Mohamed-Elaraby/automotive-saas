@php
    $currentLocale = LaravelLocalization::getCurrentLocale();
    $currentLocaleProperties = LaravelLocalization::getSupportedLocales()[$currentLocale] ?? [];
    $currentLocaleFlag = $currentLocale === 'ar' ? 'ae' : 'us';
@endphp

<div class="nav-item dropdown has-arrow flag-nav me-2 language-switcher">
    <a
        class="btn btn-menubar"
        data-bs-toggle="dropdown"
        href="javascript:void(0);"
        role="button"
        aria-label="{{ __('shared.language') }}"
    >
        <img src="{{ url('theme/img/flags/' . $currentLocaleFlag . '.svg') }}" alt="{{ $currentLocaleProperties['native'] ?? __('shared.language') }}" class="img-fluid language-switcher-flag">
    </a>
    <ul class="dropdown-menu p-2">
        @foreach(LaravelLocalization::getLocalesOrder() as $localeCode => $properties)
            @php($flag = $localeCode === 'ar' ? 'ae' : 'us')
            <li>
                <a
                    rel="alternate"
                    hreflang="{{ $localeCode }}"
                    href="{{ LaravelLocalization::getLocalizedURL($localeCode) }}"
                    class="dropdown-item d-flex align-items-center {{ $localeCode === $currentLocale ? 'active' : '' }}"
                >
                    <img src="{{ url('theme/img/flags/' . $flag . '.svg') }}" alt="{{ $properties['native'] }}" class="me-2 language-switcher-flag">
                    <span>{{ $properties['native'] }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</div>
