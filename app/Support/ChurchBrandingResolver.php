<?php

namespace App\Support;

use App\Models\Church;
use App\Models\Network;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class ChurchBrandingResolver
{
    /**
     * @return array{
     *     path: string,
     *     disk: string|null,
     *     external_url: string|null,
     *     source_church_id: string|null,
     *     source_church_name: string|null,
     *     inherited: bool,
     *     fallback: bool
     * }
     */
    public function logoAsset(?Church $church): array
    {
        if ($church !== null) {
            $resolved = $this->firstAvailableChurchAsset($church, 'logo_path');

            if ($resolved !== null) {
                return $resolved;
            }
        }

        return $this->fallbackAsset();
    }

    /**
     * @return array{
     *     path: string,
     *     disk: string|null,
     *     external_url: string|null,
     *     source_church_id: string|null,
     *     source_church_name: string|null,
     *     inherited: bool,
     *     fallback: bool
     * }
     */
    public function iconAsset(?Church $church): array
    {
        if ($church !== null) {
            $resolved = $this->firstAvailableChurchAsset($church, 'icon_path');

            if ($resolved !== null) {
                return $resolved;
            }
        }

        return $this->logoAsset($church);
    }

    /**
     * @return array{
     *     logo_path: string,
     *     logo_url: string,
     *     icon_url: string,
     *     icon_path: string,
     *     logo_source_church_id: string|null,
     *     logo_source_church_name: string|null,
     *     logo_inherited: bool,
     *     logo_fallback: bool
     * }
     */
    public function sharedLogo(?Church $church): array
    {
        $logo = $this->logoAsset($church);
        $icon = $this->iconAsset($church);

        return [
            'logo_path' => $logo['fallback'] ? '' : $logo['path'],
            'icon_path' => $icon['fallback'] ? '' : $icon['path'],
            'logo_url' => route('branding.logo', absolute: false),
            'icon_url' => route('branding.icon', absolute: false),
            'logo_source_church_id' => $logo['source_church_id'],
            'logo_source_church_name' => $logo['source_church_name'],
            'logo_inherited' => $logo['inherited'],
            'logo_fallback' => $logo['fallback'],
        ];
    }

    /**
     * @return array{contents: string|null, mime_type: string, external_url: string|null}
     */
    public function logoContents(?Church $church): array
    {
        return $this->assetContents($this->logoAsset($church));
    }

    /** @return array{contents: string|null, mime_type: string, external_url: string|null} */
    public function iconContents(?Church $church): array
    {
        return $this->assetContents($this->iconAsset($church));
    }

    /**
     * @param  array{path: string, disk: string|null, external_url: string|null, source_church_id: string|null, source_church_name: string|null, inherited: bool, fallback: bool}  $asset
     * @return array{contents: string|null, mime_type: string, external_url: string|null}
     */
    private function assetContents(array $asset): array
    {
        if ($asset['external_url'] !== null) {
            return [
                'contents' => null,
                'mime_type' => 'image/png',
                'external_url' => $asset['external_url'],
            ];
        }

        if ($asset['disk'] !== null) {
            try {
                $disk = Storage::disk($asset['disk']);

                return [
                    'contents' => $disk->get($asset['path']),
                    'mime_type' => $disk->mimeType($asset['path']) ?: 'image/png',
                    'external_url' => null,
                ];
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        $fallback = $this->fallbackAsset();
        $contents = file_get_contents($fallback['path']);

        return [
            'contents' => $contents === false ? null : $contents,
            'mime_type' => 'image/png',
            'external_url' => null,
        ];
    }

    /**
     * @return array{
     *     path: string,
     *     disk: string|null,
     *     external_url: string|null,
     *     source_church_id: string|null,
     *     source_church_name: string|null,
     *     inherited: bool,
     *     fallback: bool
     * }|null
     */
    private function firstAvailableChurchAsset(Church $church, string $pathKey): ?array
    {
        /** @var Collection<int, Church> $currentLevel */
        $currentLevel = collect([$church]);
        $visitedChurchIds = [];
        $mediaDisk = (string) config('media.disk');

        while ($currentLevel->isNotEmpty()) {
            $currentLevel = $currentLevel
                ->reject(fn (Church $candidate): bool => isset($visitedChurchIds[$candidate->id]))
                ->values();

            if ($currentLevel->isEmpty()) {
                break;
            }

            foreach ($currentLevel as $candidate) {
                $visitedChurchIds[$candidate->id] = true;
                $candidate->loadMissing('settings');
                $assetPath = data_get($candidate->settings?->options, 'branding.'.$pathKey);

                if (! is_string($assetPath) || $assetPath === '') {
                    continue;
                }

                $externalUrl = filter_var($assetPath, FILTER_VALIDATE_URL) ? $assetPath : null;

                if ($externalUrl === null && ! $this->storedAssetExists($mediaDisk, $assetPath)) {
                    continue;
                }

                return [
                    'path' => $assetPath,
                    'disk' => $externalUrl === null ? $mediaDisk : null,
                    'external_url' => $externalUrl,
                    'source_church_id' => $candidate->id,
                    'source_church_name' => $candidate->name,
                    'inherited' => $candidate->id !== $church->id,
                    'fallback' => false,
                ];
            }

            $currentLevel = Network::query()
                ->whereIn('child_church_id', $currentLevel->pluck('id'))
                ->with('parentChurch.settings')
                ->oldest()
                ->get()
                ->pluck('parentChurch')
                ->filter(fn (?Church $parentChurch): bool => $parentChurch !== null)
                ->unique('id')
                ->values();
        }

        return null;
    }

    private function storedAssetExists(string $disk, string $path): bool
    {
        try {
            return Storage::disk($disk)->exists($path);
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    /**
     * @return array{
     *     path: string,
     *     disk: null,
     *     external_url: null,
     *     source_church_id: null,
     *     source_church_name: null,
     *     inherited: false,
     *     fallback: true
     * }
     */
    private function fallbackAsset(): array
    {
        return [
            'path' => resource_path('pwa/nossa-casa-mark.png'),
            'disk' => null,
            'external_url' => null,
            'source_church_id' => null,
            'source_church_name' => null,
            'inherited' => false,
            'fallback' => true,
        ];
    }
}
