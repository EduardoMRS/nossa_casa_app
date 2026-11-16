<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\ChurchStorageSetting;
use App\Models\ClassroomMaterial;
use App\Models\EventMaterial;
use App\Models\Media;
use App\Models\Network;
use App\Models\Recording;
use App\Models\User;
use Exception;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ChurchStorageManager
{
    /**
     * Resolves the active storage setting for a church, supporting network inheritance.
     *
     * @return array{setting: ChurchStorageSetting, is_inherited: bool, source_church: ?Church}|null
     */
    public function resolveFor(?Church $church): ?array
    {
        if (! $church) {
            return null;
        }

        $own = $church->storageSetting()->first();

        if ($own && $own->enabled) {
            return [
                'setting' => $own,
                'is_inherited' => false,
                'source_church' => $church,
            ];
        }

        // Network inheritance resolution
        $visited = [$church->id];
        $currentId = $church->id;

        while ($currentId) {
            $network = Network::query()->where('child_church_id', $currentId)->first();

            if (! $network || in_array($network->parent_church_id, $visited, true)) {
                break;
            }

            $currentId = $network->parent_church_id;
            $visited[] = $currentId;

            $parentSetting = ChurchStorageSetting::query()
                ->where('church_id', $currentId)
                ->where('enabled', true)
                ->where('allow_branches', true)
                ->first();

            if ($parentSetting) {
                $parentChurch = Church::query()->find($currentId);

                return [
                    'setting' => $parentSetting,
                    'is_inherited' => true,
                    'source_church' => $parentChurch,
                ];
            }
        }

        return null;
    }

    /**
     * Resolves the disk name for a church.
     */
    public function diskNameFor(?Church $church): string
    {
        $resolved = $this->resolveFor($church);

        if ($resolved && $resolved['setting']->driver !== 'default') {
            $diskName = 'church_'.$resolved['setting']->church_id;
            $this->ensureDiskRegistered($diskName, $resolved['setting']->driver, (array) ($resolved['setting']->config ?? []));

            return $diskName;
        }

        return (string) config('media.disk', 'media');
    }

    /**
     * Returns a filesystem instance for the specified church.
     */
    public function diskFor(?Church $church): Filesystem
    {
        $diskName = $this->diskNameFor($church);

        return Storage::disk($diskName);
    }

    /**
     * Resolves and registers a disk on demand by disk name if it is a church disk.
     */
    public function resolveDiskByName(string $diskName): ?Filesystem
    {
        if (! str_starts_with($diskName, 'church_')) {
            return null;
        }

        $churchId = substr($diskName, 7);
        $setting = ChurchStorageSetting::query()->where('church_id', $churchId)->first();

        if (! $setting || ! $setting->enabled || $setting->driver === 'default') {
            return null;
        }

        $this->ensureDiskRegistered($diskName, $setting->driver, (array) ($setting->config ?? []));

        return Storage::disk($diskName);
    }

    /**
     * Ensures that a dynamic disk is registered in Laravel's filesystem config.
     *
     * @param  array<string, mixed>  $config
     */
    public function ensureDiskRegistered(string $diskName, string $driver, array $config): void
    {
        $diskConfig = $this->buildDiskConfig($driver, $config);

        Config::set("filesystems.disks.{$diskName}", $diskConfig);

        $tempDisks = (array) config('filesystems.temporary_url_disks', []);
        if (! in_array($diskName, $tempDisks, true)) {
            $tempDisks[] = $diskName;
            Config::set('filesystems.temporary_url_disks', $tempDisks);
        }
    }

    /**
     * Builds standard disk configuration array from driver and parameters.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function buildDiskConfig(string $driver, array $config): array
    {
        return match ($driver) {
            's3' => array_filter([
                'driver' => 's3',
                'key' => (string) ($config['key'] ?? ''),
                'secret' => (string) ($config['secret'] ?? ''),
                'region' => (string) ($config['region'] ?? 'us-east-1'),
                'bucket' => (string) ($config['bucket'] ?? ''),
                'endpoint' => filled($config['endpoint'] ?? null) ? (string) $config['endpoint'] : null,
                'use_path_style_endpoint' => filter_var($config['use_path_style_endpoint'] ?? false, FILTER_VALIDATE_BOOL),
                'url' => filled($config['url'] ?? null) ? (string) $config['url'] : null,
                'root' => (string) ($config['root'] ?? ''),
                'throw' => true,
            ]),
            'ftp' => array_filter([
                'driver' => 'ftp',
                'host' => (string) ($config['host'] ?? ''),
                'username' => (string) ($config['username'] ?? ''),
                'password' => (string) ($config['password'] ?? ''),
                'port' => (int) ($config['port'] ?? 21),
                'root' => (string) ($config['root'] ?? ''),
                'passive' => filter_var($config['passive'] ?? true, FILTER_VALIDATE_BOOL),
                'ssl' => filter_var($config['ssl'] ?? false, FILTER_VALIDATE_BOOL),
                'timeout' => 30,
                'throw' => true,
            ]),
            'sftp' => array_filter([
                'driver' => 'sftp',
                'host' => (string) ($config['host'] ?? ''),
                'username' => (string) ($config['username'] ?? ''),
                'password' => filled($config['password'] ?? null) ? (string) $config['password'] : null,
                'privateKey' => filled($config['private_key'] ?? null) ? (string) $config['private_key'] : null,
                'port' => (int) ($config['port'] ?? 22),
                'root' => (string) ($config['root'] ?? ''),
                'timeout' => 30,
                'throw' => true,
            ]),
            default => [
                'driver' => 'local',
                'root' => storage_path('app/private/media'),
                'throw' => true,
            ],
        };
    }

    /**
     * Tests connection for a specific driver and configuration.
     *
     * @param  array<string, mixed>  $config
     * @return array{success: bool, message: string}
     */
    public function testConnection(string $driver, array $config): array
    {
        try {
            if ($driver === 'default') {
                $probeFile = '.probe_'.Str::random(12).'.tmp';
                $disk = Storage::disk((string) config('media.disk', 'media'));
                $disk->put($probeFile, 'connection_test_ok');
                $disk->delete($probeFile);

                return [
                    'success' => true,
                    'message' => __('settings.storage.connection_successful'),
                ];
            }

            if ($driver === 's3') {
                if (empty($config['key']) || empty($config['secret']) || empty($config['bucket'])) {
                    throw new Exception(__('settings.storage.errors.missing_s3_credentials'));
                }

                $probeName = 'test_s3_probe_'.Str::random(10);
                $this->ensureDiskRegistered($probeName, 's3', $config);
                $disk = Storage::disk($probeName);
                $probeFile = '.probe_'.Str::random(12).'.tmp';
                $disk->put($probeFile, 'probe_content');
                $disk->delete($probeFile);
                Storage::purge($probeName);

                return [
                    'success' => true,
                    'message' => __('settings.storage.connection_successful'),
                ];
            }

            if ($driver === 'ftp' || $driver === 'sftp') {
                if (empty($config['host']) || empty($config['username'])) {
                    throw new Exception(__('settings.storage.errors.missing_ftp_credentials'));
                }

                $probeName = 'test_ftp_probe_'.Str::random(10);
                $this->ensureDiskRegistered($probeName, $driver, $config);
                $disk = Storage::disk($probeName);
                $probeFile = '.probe_'.Str::random(12).'.tmp';
                $disk->put($probeFile, 'probe_content');
                $disk->delete($probeFile);
                Storage::purge($probeName);

                return [
                    'success' => true,
                    'message' => __('settings.storage.connection_successful'),
                ];
            }

            if ($driver === 'google_drive') {
                return $this->testGoogleDriveConnection($config);
            }

            if ($driver === 'onedrive') {
                return $this->testOneDriveConnection($config);
            }

            throw new Exception(__('settings.storage.errors.unsupported_driver'));
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage() ?: __('settings.storage.connection_failed'),
            ];
        }
    }

    /**
     * Tests Google Drive API connection.
     *
     * @param  array<string, mixed>  $config
     * @return array{success: bool, message: string}
     */
    protected function testGoogleDriveConnection(array $config): array
    {
        $clientId = (string) ($config['client_id'] ?? '');
        $clientSecret = (string) ($config['client_secret'] ?? '');
        $refreshToken = (string) ($config['refresh_token'] ?? '');

        if (empty($clientId) || empty($clientSecret) || empty($refreshToken)) {
            throw new Exception(__('settings.storage.errors.missing_google_drive_credentials'));
        }

        // Exchange refresh token for access token
        $response = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        if (! $response->successful()) {
            $err = $response->json('error_description') ?: $response->json('error') ?: 'Authentication failed';
            throw new Exception(__('settings.storage.errors.google_auth_failed', ['error' => $err]));
        }

        $accessToken = $response->json('access_token');
        if (! $accessToken) {
            throw new Exception(__('settings.storage.errors.google_auth_failed', ['error' => 'No access token returned']));
        }

        // Verify drive access via about/files query
        $testResponse = Http::withToken($accessToken)->timeout(15)->get('https://www.googleapis.com/drive/v3/about', [
            'fields' => 'user,storageQuota',
        ]);

        if (! $testResponse->successful()) {
            throw new Exception(__('settings.storage.errors.google_drive_api_error'));
        }

        return [
            'success' => true,
            'message' => __('settings.storage.connection_successful'),
        ];
    }

    /**
     * Tests Microsoft OneDrive / Graph API connection.
     *
     * @param  array<string, mixed>  $config
     * @return array{success: bool, message: string}
     */
    protected function testOneDriveConnection(array $config): array
    {
        $clientId = (string) ($config['client_id'] ?? '');
        $clientSecret = (string) ($config['client_secret'] ?? '');
        $tenantId = (string) ($config['tenant_id'] ?? 'common');

        if (empty($clientId) || empty($clientSecret)) {
            throw new Exception(__('settings.storage.errors.missing_onedrive_credentials'));
        }

        $tokenUrl = "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token";

        $response = Http::asForm()->timeout(15)->post($tokenUrl, [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'scope' => 'https://graph.microsoft.com/.default',
            'grant_type' => 'client_credentials',
        ]);

        if (! $response->successful()) {
            $err = $response->json('error_description') ?: $response->json('error') ?: 'Authentication failed';
            throw new Exception(__('settings.storage.errors.onedrive_auth_failed', ['error' => $err]));
        }

        $accessToken = $response->json('access_token');
        if (! $accessToken) {
            throw new Exception(__('settings.storage.errors.onedrive_auth_failed', ['error' => 'No access token returned']));
        }

        $graphResponse = Http::withToken($accessToken)->timeout(15)->get('https://graph.microsoft.com/v1.0/drive');

        if (! $graphResponse->successful() && $graphResponse->status() !== 404) {
            // Some personal vs enterprise tenant endpoints vary, but valid auth returns 200 or specific drive response
            $err = $graphResponse->json('error.message') ?: 'Drive query returned status '.$graphResponse->status();
            throw new Exception(__('settings.storage.errors.onedrive_auth_failed', ['error' => $err]));
        }

        return [
            'success' => true,
            'message' => __('settings.storage.connection_successful'),
        ];
    }

    /**
     * Calculates the total storage used by the church in bytes across all modules.
     */
    public function usedBytesFor(?Church $church): int
    {
        if (! $church) {
            return 0;
        }

        $mediaSize = (int) Media::query()->where('church_id', $church->id)->sum('size');

        $eventMaterialSize = (int) EventMaterial::query()
            ->whereHas('event', fn ($query) => $query->where('church_id', $church->id))
            ->sum('size');

        $classroomMaterialSize = (int) ClassroomMaterial::query()
            ->whereHas('classroom', fn ($query) => $query->where('church_id', $church->id))
            ->sum('size');

        $recordingSize = (int) Recording::query()
            ->whereNull('media_id')
            ->whereHas('liveStream', fn ($query) => $query->where('church_id', $church->id))
            ->sum('size');

        return $mediaSize + $eventMaterialSize + $classroomMaterialSize + $recordingSize;
    }

    /**
     * Generates a storage quota summary for the church.
     *
     * @return array{
     *     is_custom: bool,
     *     used_bytes: int,
     *     used_formatted: string,
     *     limit_bytes: int,
     *     limit_formatted: string,
     *     usage_percentage: float,
     *     is_exceeded: bool,
     *     should_show_warning: bool
     * }
     */
    public function storageQuotaSummary(?Church $church, ?User $user = null): array
    {
        $resolved = $this->resolveFor($church);
        $isCustom = $resolved !== null && $resolved['setting']->driver !== 'default';
        $usedBytes = $this->usedBytesFor($church);
        $limitBytes = (int) config('filesystems.church_storage_limit_bytes', 2147483648);
        $usagePercentage = $limitBytes > 0 ? round(($usedBytes / $limitBytes) * 100, 1) : 0.0;
        $isExceeded = ! $isCustom && $usedBytes > $limitBytes;

        $targetRoles = [
            UserRole::MEDIA,
            UserRole::LEADER,
            UserRole::CHURCH_LEADER,
            UserRole::SUPERADMIN,
            UserRole::SYSTEM,
        ];

        $shouldShowWarning = $isExceeded && $user !== null && in_array($user->role, $targetRoles, true);

        return [
            'is_custom' => $isCustom,
            'used_bytes' => $usedBytes,
            'used_formatted' => $this->formatBytes($usedBytes),
            'limit_bytes' => $limitBytes,
            'limit_formatted' => $this->formatBytes($limitBytes),
            'usage_percentage' => $usagePercentage,
            'is_exceeded' => $isExceeded,
            'should_show_warning' => $shouldShowWarning,
        ];
    }

    /**
     * Formats bytes into a human-readable size string.
     */
    public function formatBytes(int|float $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision).' '.$units[$pow];
    }
}
