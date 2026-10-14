<?php
namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaOptimizer
{
    public static function createPreview(string $diskName, string $originalPath): ?string
    {
        $disk = Storage::disk($diskName);

        if (!$disk->exists($originalPath)) {
            return null;
        }

        $extension = strtolower(pathinfo($originalPath, PATHINFO_EXTENSION));

        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return null;
        }

        $stream = $disk->readStream($originalPath);
        if (!$stream) {
            return null;
        }

        $binaryData = stream_get_contents($stream);
        fclose($stream);

        $image = @imagecreatefromstring($binaryData);
        if (!$image) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $maxWidth = 1200;

        if ($width > $maxWidth) {
            $newWidth = $maxWidth;
            $newHeight = (int) floor($height * ($maxWidth / $width));

            $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
            imagealphablending($resizedImage, false);
            imagesavealpha($resizedImage, true);
            imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $resizedImage;
        }

        ob_start();
        imagewebp($image, null, 80);
        $compressedData = ob_get_clean();
        imagedestroy($image);

        if (!$compressedData) {
            return null;
        }

        $filename = 'previews/' . Str::uuid() . '.webp';

        return $disk->put($filename, $compressedData) ? $filename : null;
    }
}
