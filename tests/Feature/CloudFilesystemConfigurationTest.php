<?php

use App\Support\S3TemporaryUrlGenerator;
use App\Traits\UploadsMedia;
use AzureOss\Storage\BlobLaravel\AzureStorageBlobAdapter;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

test('S3 compatible providers are registered as storage disks', function (string $disk): void {
    config()->set("filesystems.disks.{$disk}.key", 'test-access-key');
    config()->set("filesystems.disks.{$disk}.secret", 'test-secret-key');
    config()->set("filesystems.disks.{$disk}.bucket", 'test-bucket');

    expect(Storage::disk($disk))->toBeInstanceOf(AwsS3V3Adapter::class);
})->with([
    'Amazon S3' => 's3',
    'MinIO' => 'minio',
    'MEGA S4' => 'mega_s4',
    'Google Cloud interoperability API' => 'google_cloud',
    'Akamai Object Storage' => 'akamai',
]);

test('Azure Blob Storage is registered as a storage disk', function (): void {
    config()->set('filesystems.disks.azure.connection_string', implode(';', [
        'DefaultEndpointsProtocol=https',
        'AccountName=example',
        'AccountKey='.base64_encode('test-secret-key'),
        'EndpointSuffix=core.windows.net',
    ]));
    config()->set('filesystems.disks.azure.container', 'test-container');

    expect(Storage::disk('azure'))
        ->toBeInstanceOf(AzureStorageBlobAdapter::class);
});

test('uploaded media can target any configured storage disk', function (): void {
    Storage::fake('mega_s4');
    config()->set('media.disk', 'mega_s4');

    $uploader = new class
    {
        use UploadsMedia;

        public function upload(UploadedFile $file): ?string
        {
            return $this->handleMediaUpload($file, 'uploads');
        }
    };

    $path = $uploader->upload(
        UploadedFile::fake()->createWithContent('example.txt', 'contents'),
    );

    expect($path)->not->toBeNull();
    Storage::disk('mega_s4')->assertExists($path, 'contents');
});

test('local storage disks provide temporary URLs', function (string $disk): void {
    $path = 'temporary/'.Str::lower((string) Str::ulid()).'.txt';
    Storage::disk($disk)->put($path, 'contents');

    try {
        $url = Storage::disk($disk)->temporaryUrl(
            $path,
            now()->addMinutes(5),
        );

        expect($url)
            ->toContain('signature=')
            ->toContain('expires=');

        $this->get($url)->assertOk();
    } finally {
        Storage::disk($disk)->delete($path);
    }
})->with([
    'local',
    'public',
    'media',
    'recordings',
]);

test('S3 compatible storage disks provide temporary URLs', function (string $disk): void {
    config()->set("filesystems.disks.{$disk}.key", 'test-access-key');
    config()->set("filesystems.disks.{$disk}.secret", 'test-secret-key');
    config()->set("filesystems.disks.{$disk}.bucket", 'test-bucket');
    config()->set("filesystems.disks.{$disk}.temporary_url", null);

    $url = Storage::disk($disk)->temporaryUrl(
        'temporary/example.txt',
        now()->addMinutes(5),
    );

    expect($url)->toContain('X-Amz-Signature=');
})->with([
    'Amazon S3' => 's3',
    'MinIO' => 'minio',
    'MEGA S4' => 'mega_s4',
    'Google Cloud interoperability API' => 'google_cloud',
    'Akamai Object Storage' => 'akamai',
]);

test('Azure Blob Storage provides temporary URLs', function (): void {
    config()->set('filesystems.disks.azure.connection_string', implode(';', [
        'DefaultEndpointsProtocol=https',
        'AccountName=example',
        'AccountKey='.base64_encode('test-secret-key'),
        'EndpointSuffix=core.windows.net',
    ]));
    config()->set('filesystems.disks.azure.container', 'test-container');

    $url = Storage::disk('azure')->temporaryUrl(
        'temporary/example.txt',
        now()->addMinutes(5),
    );

    expect($url)->toContain('sig=');
});

test('S3 URLs are signed directly for the browser endpoint', function (): void {
    config()->set('filesystems.disks.minio.key', 'test-access-key');
    config()->set('filesystems.disks.minio.secret', 'test-secret-key');
    config()->set('filesystems.disks.minio.bucket', 'test-bucket');
    config()->set('filesystems.disks.minio.endpoint', 'http://minio:9000');
    config()->set('filesystems.disks.minio.temporary_url', 'https://nossa.test');

    $url = app(S3TemporaryUrlGenerator::class)->generate(
        'minio',
        'recordings/service.mp4',
        now()->addMinutes(5),
    );

    expect($url)
        ->toStartWith('https://nossa.test/test-bucket/recordings/service.mp4?')
        ->toContain('X-Amz-Signature=');
});
