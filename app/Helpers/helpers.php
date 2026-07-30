<?php

use \Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;

if(!function_exists('getFileMetadata')) {
    /**
     * Helper to get file metadata
     * @param UploadedFile|string|null $filepath filepath, file base64 string, or URL
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
            $disks = config('filesystems.disks');
            foreach ($disks as $diskName => $diskConfig) {
                if (Storage::disk($diskName)->exists($filepath)) {
                    $metadata['origin'] = 'cloud';
                    $metadata['handler'] = function () use ($diskName, $filepath) {
                        return Storage::disk($diskName)->readStream($filepath);
                    };
                    $filepath = Storage::disk($diskName)->path($filepath);
                    break;
                }
            }
        }

        $metadata['exists'] = true;
        $metadata['mime_type'] = mime_content_type($filepath);
        $metadata['size'] = filesize($filepath);
        $metadata['path'] = realpath($filepath);
        $metadata['name'] = basename($filepath);

        return $metadata;        
    }
}

if(!function_exists('genUrl')) {
    /**
     * Helper to generate a secure URL for a file path
     * TODO: reduce the size of the encrypted string to make it more user-friendly
     * @param string $filePath The file path to encrypt and generate a URL for
     * @return string The generated secure URL
     */
    function genUrl($filePath)
    {
        $encryptedPath = Crypt::encryptString($filePath);
        $url = route('secure-file', ['encryptedFile' => $encryptedPath]);
        return $url;
    }
}
