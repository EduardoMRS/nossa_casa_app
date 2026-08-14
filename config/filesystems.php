<?php

$configuredDisks = array_values(array_unique(array_filter([
    'local',
    'public',
    'media',
    'recordings',
    env('FILESYSTEM_DISK', 'local'),
    env('MEDIA_DISK', 'media'),
    env('MEDIA_ARCHIVE_DISK', 'recordings'),
    ...array_map('trim', explode(',', (string) env('FILESYSTEM_METADATA_DISKS', ''))),
], fn (mixed $disk): bool => is_string($disk) && $disk !== '')));

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    'metadata_disks' => $configuredDisks,

    'temporary_url_disks' => $configuredDisks,

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim((string) env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'serve' => false,
            'throw' => false,
            'report' => false,
        ],

        'media' => [
            'driver' => 'local',
            'root' => storage_path('app/private/media'),
            'serve' => false,
            'throw' => true,
            'report' => true,
        ],

        'recordings' => [
            'driver' => 'local',
            'root' => storage_path('app/private/recordings'),
            'serve' => false,
            'throw' => true,
            'report' => true,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'temporary_url' => env('AWS_TEMPORARY_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => true,
            'report' => true,
        ],

        'minio' => [
            'driver' => 's3',
            'key' => env('MINIO_ACCESS_KEY_ID'),
            'secret' => env('MINIO_SECRET_ACCESS_KEY'),
            'region' => env('MINIO_DEFAULT_REGION', 'us-east-1'),
            'bucket' => env('MINIO_BUCKET'),
            'url' => env('MINIO_URL'),
            'temporary_url' => env('MINIO_TEMPORARY_URL'),
            'endpoint' => env('MINIO_ENDPOINT', 'http://127.0.0.1:9000'),
            'use_path_style_endpoint' => env('MINIO_USE_PATH_STYLE_ENDPOINT', true),
            'throw' => true,
            'report' => true,
        ],

        'mega_s4' => [
            'driver' => 's3',
            'key' => env('MEGA_S4_ACCESS_KEY_ID'),
            'secret' => env('MEGA_S4_SECRET_ACCESS_KEY'),
            'region' => env('MEGA_S4_DEFAULT_REGION', 'eu-amsterdam'),
            'bucket' => env('MEGA_S4_BUCKET'),
            'url' => env('MEGA_S4_URL'),
            'temporary_url' => env('MEGA_S4_TEMPORARY_URL'),
            'endpoint' => env('MEGA_S4_ENDPOINT', 'https://s3.eu-amsterdam.megas4.com'),
            'use_path_style_endpoint' => env('MEGA_S4_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => true,
            'report' => true,
        ],

        'google_cloud' => [
            'driver' => 's3',
            'key' => env('GOOGLE_CLOUD_HMAC_ACCESS_KEY_ID'),
            'secret' => env('GOOGLE_CLOUD_HMAC_SECRET_ACCESS_KEY'),
            'region' => env('GOOGLE_CLOUD_DEFAULT_REGION', 'auto'),
            'bucket' => env('GOOGLE_CLOUD_BUCKET'),
            'url' => env('GOOGLE_CLOUD_URL'),
            'temporary_url' => env('GOOGLE_CLOUD_TEMPORARY_URL'),
            'endpoint' => env('GOOGLE_CLOUD_ENDPOINT', 'https://storage.googleapis.com'),
            'use_path_style_endpoint' => env('GOOGLE_CLOUD_USE_PATH_STYLE_ENDPOINT', true),
            'throw' => true,
            'report' => true,
        ],

        'akamai' => [
            'driver' => 's3',
            'key' => env('AKAMAI_ACCESS_KEY_ID'),
            'secret' => env('AKAMAI_SECRET_ACCESS_KEY'),
            'region' => env('AKAMAI_DEFAULT_REGION', 'us-east-1'),
            'bucket' => env('AKAMAI_BUCKET'),
            'url' => env('AKAMAI_URL'),
            'temporary_url' => env('AKAMAI_TEMPORARY_URL'),
            'endpoint' => env('AKAMAI_ENDPOINT'),
            'use_path_style_endpoint' => env('AKAMAI_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => true,
            'report' => true,
        ],

        'azure' => [
            'driver' => 'azure-storage-blob',
            'connection_string' => env('AZURE_STORAGE_CONNECTION_STRING'),
            'container' => env('AZURE_STORAGE_CONTAINER'),
            'prefix' => env('AZURE_STORAGE_PREFIX', ''),
            'temporary_url' => env('AZURE_STORAGE_TEMPORARY_URL') ?: null,
            'is_public_container' => env('AZURE_STORAGE_PUBLIC', false),
            'throw' => true,
            'report' => true,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
