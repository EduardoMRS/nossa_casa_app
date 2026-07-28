<?php

use \Illuminate\Http\UploadedFile;

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
