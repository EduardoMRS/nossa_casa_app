<?php

if(!function_exists('getFileMetadata')) {
    /**
     * Helper to get file metadata
     * @param mixed $filepath filepath or url
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