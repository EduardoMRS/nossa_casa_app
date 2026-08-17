<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;
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

if (! function_exists('temporaryStorageUrl')) {
    function temporaryStorageUrl(string $filePath, DateTimeInterface $expiration, string $disk): string
    {
        $encryptedPath = Crypt::encryptString(json_encode([
            'disk' => $disk,
            'path' => $filePath,
        ], JSON_THROW_ON_ERROR));

        return URL::temporarySignedRoute(
            'secure-file',
            $expiration,
            ['encryptedFile' => $encryptedPath],
        );
    }
}

if (! function_exists('genUrl')) {
    /**
     * Helper to generate a secure URL for a file path
     * TODO: reduce the size of the encrypted string to make it more user-friendly
     *
     * @param  string  $filePath  The file path to encrypt and generate a URL for
     * @return string The generated secure URL
     */
    function genUrl($filePath, ?string $disk = null): ?string
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
        );
    }
}

if (!function_exists('urlBase')) {
    /**
     * Generates the base URL for a given subdomain
     * 
     * @param string|null $subdomain The subdomain to prepend to the base URL
     * @return string The generated base URL with the optional subdomain
     */
    function urlBase($subdomain = null): ?string
    {
        $base = config('app.url');
        $isHttps = str_starts_with($base, 'https://');
        return ($isHttps ? 'https://' : 'http://') . ($subdomain? $subdomain . '.' : '') . parse_url($base, PHP_URL_HOST);
    }
}

if (!function_exists('domainBase')) {
    /**
     * Generates the base domain for a given subdomain
     * 
     * @param string|null $subdomain The subdomain to prepend to the base domain
     * @return string The generated base domain with the optional subdomain
     */
    function domainBase($subdomain = null): ?string
    {
        return ($subdomain ? $subdomain . '.' : '') . parse_url(config('app.url'), PHP_URL_HOST);
    }
}
