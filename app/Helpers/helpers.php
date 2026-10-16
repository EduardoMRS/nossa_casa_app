<?php

use App\Models\Church;
use App\Support\ChurchDomainContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use League\Flysystem\AwsS3V3\PortableVisibilityConverter;

if (! function_exists('getFileMetadata')) {
    /**
     * Helper to get file metadata
     *
     * @param  UploadedFile|string|null  $filepath  filepath, file base64 string, or URL
     * @return array{
     *  exists: bool,
     *  mime_type:string,
     *  handler: callable,
     *  size: int,
     *  origin: string,
     *  path: string,
     *  name: string,
     * }
     */
    function getFileMetadata($filepath)
    {
        $metadata = [
            'exists' => false,
            'mime_type' => '',
            'handler' => null,
            'size' => 0,
            'origin' => '',
            'path' => '',
            'name' => '',
        ];

        if (filter_var($filepath, FILTER_VALIDATE_URL)) {
            $metadata['origin'] = 'url';
            $metadata['handler'] = function () use ($filepath) {
                return fopen($filepath, 'r');
            };

            $path = (string) parse_url($filepath, PHP_URL_PATH);
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

            $metadata['exists'] = true;
            $metadata['mime_type'] = match ($extension) {
                'pdf' => 'application/pdf',
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                'svg' => 'image/svg+xml',
                'txt' => 'text/plain',
                'csv' => 'text/csv',
                'json' => 'application/json',
                'xml' => 'application/xml',
                default => 'application/octet-stream',
            };
            $metadata['size'] = 0;
            $metadata['path'] = $filepath;
            $metadata['name'] = basename($path !== '' ? $path : $filepath);

            return $metadata;
        } elseif ($filepath instanceof UploadedFile && $filepath->isValid()) {
            $metadata['origin'] = 'local';
            $metadata['handler'] = function () use ($filepath) {
                return fopen($filepath->getRealPath(), 'r');
            };
            $filepath = $filepath->getRealPath();
        } elseif (file_exists($filepath)) {
            $metadata['origin'] = 'local';
            $metadata['handler'] = function () use ($filepath) {
                return fopen($filepath, 'r');
            };
        } else {
            $disks = config('filesystems.disks', []);
            $metadataDisks = config('filesystems.metadata_disks', []);

            if (! is_array($disks) || ! is_array($metadataDisks)) {
                return $metadata;
            }

            foreach ($metadataDisks as $diskName) {
                if (! is_string($diskName)) {
                    continue;
                }

                $diskConfig = $disks[$diskName] ?? null;

                if (! is_array($diskConfig)) {
                    continue;
                }

                $driver = $diskConfig['driver'] ?? null;

                if ($driver === 's3'
                    && (! class_exists(PortableVisibilityConverter::class)
                        || blank($diskConfig['bucket'] ?? null))) {
                    continue;
                }

                try {
                    $disk = Storage::disk($diskName);

                    if (! $disk->exists($filepath)) {
                        continue;
                    }

                    $metadata['exists'] = true;
                    $metadata['origin'] = $driver === 'local' ? 'local' : 'cloud';
                    $metadata['handler'] = function () use ($diskName, $filepath) {
                        return Storage::disk($diskName)->readStream($filepath);
                    };
                    $metadata['mime_type'] = $disk->mimeType($filepath) ?: 'application/octet-stream';
                    $metadata['size'] = $disk->size($filepath);
                    $metadata['path'] = $driver === 'local' ? $disk->path($filepath) : $filepath;
                    $metadata['name'] = basename($filepath);

                    return $metadata;
                } catch (Throwable) {
                    continue;
                }
            }

            return $metadata;
        }

        $metadata['exists'] = true;
        $metadata['mime_type'] = mime_content_type($filepath);
        $metadata['size'] = filesize($filepath);
        $metadata['path'] = realpath($filepath);
        $metadata['name'] = basename($filepath);

        return $metadata;
    }
}

if (! function_exists('encodeSecureFileToken')) {
    /**
     * Generates a compact authenticated encrypted token for a storage file path.
     */
    function encodeSecureFileToken(string $disk, string $filePath, DateTimeInterface $expiration): string
    {
        $payload = $disk.'|'.$expiration->getTimestamp().'|'.$filePath;
        $key = hash('sha256', (string) config('app.key'), true);
        $iv = random_bytes(12);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $payload,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16,
        );

        $binary = $iv.$tag.$ciphertext;

        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }
}

if (! function_exists('decodeSecureFileToken')) {
    /**
     * Decodes and validates a secure file token, returning disk and path if valid and not expired.
     *
     * @return array{disk: string, path: string}|null
     */
    function decodeSecureFileToken(string $token): ?array
    {
        // 1. Compact AES-256-GCM format
        $padded = str_pad($token, strlen($token) + ((4 - (strlen($token) % 4)) % 4), '=');
        $binary = base64_decode(strtr($padded, '-_', '+/'), true);

        if ($binary !== false && strlen($binary) >= 29) {
            $iv = substr($binary, 0, 12);
            $tag = substr($binary, 12, 16);
            $ciphertext = substr($binary, 28);
            $key = hash('sha256', (string) config('app.key'), true);

            $plaintext = openssl_decrypt(
                $ciphertext,
                'aes-256-gcm',
                $key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag,
            );

            if ($plaintext !== false) {
                $parts = explode('|', $plaintext, 3);
                if (count($parts) === 3) {
                    [$disk, $expires, $path] = $parts;
                    if ((int) $expires >= time()) {
                        return [
                            'disk' => $disk,
                            'path' => $path,
                        ];
                    }
                }
            }
        }

        // 2. Legacy fallback for tokens created with Crypt::encryptString
        try {
            $data = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
            if (is_array($data) && isset($data['disk'], $data['path'])) {
                return [
                    'disk' => (string) $data['disk'],
                    'path' => (string) $data['path'],
                ];
            }
        } catch (Throwable) {
            // Not a legacy token either
        }

        return null;
    }
}

if (! function_exists('resolveStorageBaseUrl')) {
    /**
     * Resolves the base URL using the church's domain when available, falling back to the portal URL.
     */
    function resolveStorageBaseUrl(Church|string|null $church = null): string
    {
        if (is_string($church) && Str::isUlid($church)) {
            static $churchDomainCache = [];
            if (! array_key_exists($church, $churchDomainCache)) {
                $churchDomainCache[$church] = Church::query()->whereKey($church)->value('domain');
            }
            $domain = $churchDomainCache[$church];
            if (filled($domain)) {
                $scheme = Str::contains($domain, ['localhost', '127.0.0.1']) ? 'http' : 'https';

                return $scheme.'://'.$domain;
            }
        } elseif ($church instanceof Church && filled($church->domain)) {
            $scheme = Str::contains($church->domain, ['localhost', '127.0.0.1']) ? 'http' : 'https';

            return $scheme.'://'.$church->domain;
        }

        $currentChurch = app()->bound(ChurchDomainContext::class)
            ? app(ChurchDomainContext::class)->church()
            : null;

        if ($currentChurch instanceof Church && filled($currentChurch->domain)) {
            $scheme = Str::contains($currentChurch->domain, ['localhost', '127.0.0.1']) ? 'http' : 'https';

            return $scheme.'://'.$currentChurch->domain;
        }

        return (string) config('app.url');
    }
}

if (! function_exists('temporaryStorageUrl')) {
    function temporaryStorageUrl(
        string $filePath,
        DateTimeInterface $expiration,
        string $disk,
        Church|string|null $church = null,
    ): string {
        $token = encodeSecureFileToken($disk, $filePath, $expiration);
        $baseUrl = resolveStorageBaseUrl($church);

        return rtrim($baseUrl, '/').'/d/'.$token;
    }
}

if (! function_exists('genUrl')) {
    /**
     * Helper to generate a secure URL for a file path.
     * Uses the church's domain when available, falling back to the portal.
     *
     * @param  string  $filePath  The file path to generate a URL for
     * @param  string|null  $disk  The storage disk
     * @param  Church|string|null  $church  Optional church or church ID
     * @return string The generated secure URL
     */
    function genUrl($filePath, ?string $disk = null, Church|string|null $church = null): ?string
    {
        if (! is_string($filePath) || $filePath === '') {
            return null;
        }

        if (filter_var($filePath, FILTER_VALIDATE_URL)) {
            return $filePath;
        }

        return temporaryStorageUrl(
            $filePath,
            now()->addDay(),
            $disk ?? (string) config('media.disk'),
            $church,
        );
    }
}

if (! function_exists('urlBase')) {
    /**
     * Generates the base URL for a given subdomain
     *
     * @param  string|null  $subdomain  The subdomain to prepend to the base URL
     * @return string The generated base URL with the optional subdomain
     */
    function urlBase($subdomain = null): ?string
    {
        $base = config('app.url');
        $isHttps = str_starts_with($base, 'https://');

        return ($isHttps ? 'https://' : 'http://').($subdomain ? $subdomain.'.' : '').parse_url($base, PHP_URL_HOST);
    }
}

if (! function_exists('domainBase')) {
    /**
     * Generates the base domain for a given subdomain
     *
     * @param  string|null  $subdomain  The subdomain to prepend to the base domain
     * @return string The generated base domain with the optional subdomain
     */
    function domainBase($subdomain = null): ?string
    {
        return ($subdomain ? $subdomain.'.' : '').parse_url(config('app.url'), PHP_URL_HOST);
    }
}
