<?php

namespace App\Support;

use App\Models\Church;
use App\Models\Network;
use App\Models\Setting;

final class ChurchPortalResolver
{
    /**
     * Resolve effective portal settings for a church, walking up network hierarchy
     * if the church has no settings and an ancestor propagates settings.
     *
     * @return array<string, mixed>
     */
    public function resolve(?Church $church): array
    {
        if ($church === null) {
            return $this->defaults();
        }

        $church->loadMissing('settings');
        $setting = $church->settings ?? Setting::query()->where('church_id', $church->id)->first();
        $portal = $setting?->options['portal'] ?? null;

        if (is_array($portal) && ! empty($portal)) {
            return $this->mergeWithDefaults($portal, false, null);
        }

        $ancestor = $this->findPropagatingAncestor($church);

        if ($ancestor !== null) {
            $ancestor->loadMissing('settings');
            $ancestorSetting = $ancestor->settings ?? Setting::query()->where('church_id', $ancestor->id)->first();
            $ancestorPortal = $ancestorSetting?->options['portal'] ?? null;

            if (is_array($ancestorPortal)) {
                return $this->mergeWithDefaults($ancestorPortal, true, $ancestor->name);
            }
        }

        return $this->defaults();
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'header_menu' => [
                ['key' => 'home', 'enabled' => true],
                ['key' => 'posts', 'enabled' => true],
                ['key' => 'events', 'enabled' => true],
                ['key' => 'groups', 'enabled' => true],
                ['key' => 'gallery', 'enabled' => true],
                ['key' => 'library', 'enabled' => true],
                ['key' => 'give', 'enabled' => true],
                ['key' => 'network', 'enabled' => true],
            ],
            'home_layout' => 'two_columns',
            'home_sections' => [
                'banner' => true,
                'next_event' => true,
                'daily_verse' => true,
                'featured_events' => true,
                'latest_posts' => true,
                'latest_media' => true,
                'calendar' => true,
                'prayer_contact' => true,
                'location_map' => true,
            ],
            'footer' => [
                'message' => '',
                'links' => [],
            ],
            'custom_css' => '',
            'propagate_to_branches' => true,
            'is_inherited' => false,
            'source_church_name' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $portal
     * @return array<string, mixed>
     */
    public function mergeWithDefaults(array $portal, bool $inherited = false, ?string $sourceChurchName = null): array
    {
        $defaults = $this->defaults();

        $rawHeaderMenu = is_array($portal['header_menu'] ?? null)
            ? $portal['header_menu']
            : $defaults['header_menu'];

        $headerMenu = array_map(function ($item) {
            if (! is_array($item)) {
                return $item;
            }

            return [
                'key' => (string) ($item['key'] ?? ''),
                'enabled' => filter_var($item['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ];
        }, $rawHeaderMenu);

        $rawHomeSections = is_array($portal['home_sections'] ?? null)
            ? array_merge($defaults['home_sections'], $portal['home_sections'])
            : $defaults['home_sections'];

        $homeSections = array_map(
            fn ($val): bool => filter_var($val, FILTER_VALIDATE_BOOLEAN),
            $rawHomeSections,
        );

        $footer = is_array($portal['footer'] ?? null)
            ? array_merge($defaults['footer'], $portal['footer'])
            : $defaults['footer'];

        return [
            'header_menu' => $headerMenu,
            'home_layout' => in_array($portal['home_layout'] ?? null, ['two_columns', 'single_column'], true)
                ? $portal['home_layout']
                : $defaults['home_layout'],
            'home_sections' => $homeSections,
            'footer' => [
                'message' => (string) ($footer['message'] ?? ''),
                'links' => is_array($footer['links'] ?? null) ? $footer['links'] : [],
            ],
            'custom_css' => (string) ($portal['custom_css'] ?? ''),
            'propagate_to_branches' => filter_var($portal['propagate_to_branches'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'is_inherited' => $inherited,
            'source_church_name' => $sourceChurchName,
        ];
    }

    private function findPropagatingAncestor(Church $church): ?Church
    {
        $currentLevel = collect([$church]);
        $visited = [];

        while ($currentLevel->isNotEmpty()) {
            $currentLevel = $currentLevel
                ->reject(fn (Church $c): bool => isset($visited[$c->id]))
                ->values();

            if ($currentLevel->isEmpty()) {
                break;
            }

            foreach ($currentLevel as $candidate) {
                $visited[$candidate->id] = true;

                if ($candidate->id !== $church->id) {
                    $candidate->loadMissing('settings');
                    $portal = $candidate->settings?->options['portal'] ?? null;

                    if (is_array($portal) && ! empty($portal['propagate_to_branches'])) {
                        return $candidate;
                    }
                }
            }

            $currentLevel = Network::query()
                ->whereIn('child_church_id', $currentLevel->pluck('id'))
                ->with('parentChurch.settings')
                ->oldest()
                ->get()
                ->pluck('parentChurch')
                ->filter(fn (?Church $parent): bool => $parent !== null)
                ->unique('id')
                ->values();
        }

        return null;
    }
}
