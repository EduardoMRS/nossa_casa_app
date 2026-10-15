<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
@if ($portal && $sitemaps)
    <sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    @foreach ($sitemaps as $sitemap)
        <sitemap>
            <loc>{{ $sitemap['location'] }}</loc>
            @if (!empty($sitemap['last_modified']))
                <lastmod>{{ $sitemap['last_modified'] }}</lastmod>
            @endif
        </sitemap>
    @endforeach
    </sitemapindex>
@else
    <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    @foreach ($urls as $url)
        <url>
            <loc>{{ $url['location'] }}</loc>
            @if (!empty($url['last_modified']))
                <lastmod>{{ $url['last_modified'] }}</lastmod>
            @endif
        </url>
    @endforeach
    </urlset>
@endif
