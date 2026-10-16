<?php

namespace App\Support;

use Aws\S3\S3Client;
use DateTimeInterface;
use InvalidArgumentException;

class S3TemporaryUrlGenerator
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function generate(
        string $diskName,
        string $path,
        DateTimeInterface $expiration,
        array $options = [],
    ): string {
        $config = config("filesystems.disks.{$diskName}");

        if (! is_array($config) || ($config['driver'] ?? null) !== 's3') {
            throw new InvalidArgumentException(__('media.temporary_url_not_s3', ['disk' => $diskName]));
        }

        $endpoint = $config['temporary_url'] ?? $config['endpoint'] ?? null;

        if (! is_string($endpoint) || $endpoint === '') {
            throw new InvalidArgumentException(__('media.temporary_url_endpoint_missing', ['disk' => $diskName]));
        }

        $clientConfig = [
            'version' => 'latest',
            'region' => $config['region'] ?? 'us-east-1',
            'endpoint' => $endpoint,
            'use_path_style_endpoint' => (bool) ($config['use_path_style_endpoint'] ?? false),
        ];

        if (! empty($config['key']) && ! empty($config['secret'])) {
            $clientConfig['credentials'] = array_filter([
                'key' => $config['key'],
                'secret' => $config['secret'],
                'token' => $config['token'] ?? null,
            ]);
        }

        $root = trim((string) ($config['root'] ?? ''), '/');
        $objectKey = $root === '' ? ltrim($path, '/') : $root.'/'.ltrim($path, '/');
        $client = new S3Client($clientConfig);
        $command = $client->getCommand('GetObject', array_merge([
            'Bucket' => $config['bucket'],
            'Key' => $objectKey,
        ], $options));

        return (string) $client->createPresignedRequest($command, $expiration)->getUri();
    }
}
