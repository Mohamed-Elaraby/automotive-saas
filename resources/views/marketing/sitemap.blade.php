<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
@foreach($entries as $entry)
    <url>
        <loc>{{ $entry['loc'] }}</loc>
@foreach($entry['alternates'] as $locale => $url)
        <xhtml:link rel="alternate" hreflang="{{ $locale }}" href="{{ $url }}" />
@endforeach
        <xhtml:link rel="alternate" hreflang="x-default" href="{{ $entry['x_default'] }}" />
        <changefreq>{{ $entry['changefreq'] }}</changefreq>
        <priority>{{ $entry['priority'] }}</priority>
    </url>
@endforeach
</urlset>
