<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Builds a lightweight link preview (title/description/image/site) from a URL
 * by reading its OpenGraph/HTML metadata. Best effort: any failure returns a
 * minimal preview with just the URL and host so the chat never breaks.
 */
class LinkPreviewFetcher
{
    /**
     * @return array{url: string, host: string|null, title: string|null, description: string|null, image: string|null, site_name: string|null}|null
     */
    public function fetch(string $url): ?array
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false
            || ! in_array(Str::lower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST) ?: null;
        $fallback = [
            'url' => $url,
            'host' => $host,
            'title' => null,
            'description' => null,
            'image' => null,
            'site_name' => $host,
        ];

        if (! config('services.link_preview.enabled', true)) {
            return $fallback;
        }

        try {
            $response = Http::timeout(5)
                ->connectTimeout(3)
                ->withHeaders(['User-Agent' => (string) config('services.link_preview.user_agent', 'NossaCasaBot/1.0')])
                ->get($url);

            if (! $response->successful()) {
                return $fallback;
            }

            $html = (string) Str::of($response->body())->limit(600000, '');

            return [
                'url' => $url,
                'host' => $host,
                'title' => $this->meta($html, ['og:title', 'twitter:title']) ?? $this->title($html),
                'description' => $this->meta($html, ['og:description', 'twitter:description', 'description']),
                'image' => $this->absoluteUrl($this->meta($html, ['og:image', 'twitter:image']), $url),
                'site_name' => $this->meta($html, ['og:site_name']) ?? $host,
            ];
        } catch (\Throwable $exception) {
            Log::warning('Link preview failed.', ['exception' => $exception]);

            return $fallback;
        }
    }

    /**
     * @param  array<int, string>  $properties
     */
    private function meta(string $html, array $properties): ?string
    {
        foreach ($properties as $property) {
            $pattern = '/<meta[^>]+(?:property|name)=["\']'.preg_quote($property, '/').'["\'][^>]*>/i';

            if (preg_match($pattern, $html, $tag)
                && preg_match('/content=["\']([^"\']*)["\']/i', $tag[0], $content)) {
                $value = trim(html_entity_decode($content[1], ENT_QUOTES));

                if ($value !== '') {
                    return Str::limit($value, 300);
                }
            }
        }

        return null;
    }

    private function title(string $html): ?string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $match)) {
            $value = trim(html_entity_decode(strip_tags($match[1]), ENT_QUOTES));

            return $value !== '' ? Str::limit($value, 300) : null;
        }

        return null;
    }

    private function absoluteUrl(?string $value, string $base): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (Str::startsWith($value, ['http://', 'https://'])) {
            return $value;
        }

        $scheme = parse_url($base, PHP_URL_SCHEME);
        $host = parse_url($base, PHP_URL_HOST);

        if ($host === null) {
            return null;
        }

        return $scheme.'://'.$host.'/'.ltrim($value, '/');
    }
}
