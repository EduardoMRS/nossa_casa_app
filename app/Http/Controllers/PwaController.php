<?php

namespace App\Http\Controllers;

use App\Support\ChurchDomainContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class PwaController extends Controller
{
    public function manifest(ChurchDomainContext $context): JsonResponse
    {
        $church = $context->church();
        $branding = $church?->settings?->options['branding'] ?? [];
        $name = is_array($branding) && filled($branding['brand_name'] ?? null)
            ? $branding['brand_name']
            : config('app.name');

        return response()->json([
            'id' => '/',
            'name' => $name,
            'short_name' => mb_substr((string) $name, 0, 24),
            'description' => __('pwa.description', ['name' => $name]),
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'any',
            'background_color' => $this->color($branding, 'surface_color', '#f4f7fb'),
            'theme_color' => $this->color($branding, 'primary_color', '#342f87'),
            'icons' => [
                [
                    'src' => route('branding.icon', absolute: false),
                    'sizes' => 'any',
                    'type' => 'image/svg+xml',
                    'purpose' => 'any maskable',
                ],
                [
                    'src' => route('branding.logo', absolute: false),
                    'sizes' => 'any',
                    'purpose' => 'any',
                ],
            ],
            'shortcuts' => $church ? [
                [
                    'name' => __('pwa.bible_shortcut'),
                    'short_name' => __('pwa.bible_shortcut_short'),
                    'url' => route('library.bible', absolute: false),
                    'icons' => [
                        [
                            'src' => route('branding.icon', absolute: false),
                            'sizes' => 'any',
                            'type' => 'image/svg+xml',
                        ],
                    ],
                ],
            ] : [],
        ])->header('Content-Type', 'application/manifest+json');
    }

    public function serviceWorker(ChurchDomainContext $context): Response
    {
        $template = file_get_contents(resource_path('pwa/sw.js'));
        abort_if($template === false, 404);
        $church = $context->church();
        $branding = $church?->settings?->options['branding'] ?? [];
        $name = is_array($branding) && filled($branding['brand_name'] ?? null)
            ? $branding['brand_name']
            : config('app.name');

        $content = str_replace(
            ['__CACHE_VERSION__', '__APP_NAME__', '__NOTIFICATION_FALLBACK__', '__OPEN_ACTION__', '__OFFLINE_TITLE__', '__OFFLINE_MESSAGE__'],
            [
                json_encode((string) config('app.version'), JSON_THROW_ON_ERROR),
                json_encode((string) $name, JSON_THROW_ON_ERROR),
                json_encode(__('pwa.notification_fallback'), JSON_THROW_ON_ERROR),
                json_encode(__('pwa.open_action'), JSON_THROW_ON_ERROR),
                json_encode(__('pwa.offline_title'), JSON_THROW_ON_ERROR),
                json_encode(__('pwa.offline_message'), JSON_THROW_ON_ERROR),
            ],
            $template,
        );

        return response($content, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Service-Worker-Allowed' => '/',
        ]);
    }

    /** @param array<string, mixed> $branding */
    private function color(array $branding, string $key, string $fallback): string
    {
        $color = $branding[$key] ?? null;

        return is_string($color) && preg_match('/^#[A-Fa-f0-9]{6}$/', $color) === 1
            ? $color
            : $fallback;
    }
}
