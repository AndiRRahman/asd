<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageConverter
{
    /**
     * Konversi berkas gambar yang diunggah ke format WebP dan simpan ke disk public.
     *
     * @param UploadedFile $file
     * @param string $directory Direktori tujuan (misal: 'produk', 'banner')
     * @param int $quality Kualitas WebP (default: 85)
     * @return array{path: string, width: int, height: int}
     */
    public static function convertAndStore(UploadedFile $file, string $directory = 'produk', int $quality = 85): array
    {
        $realPath = $file->getRealPath();
        [$width, $height, $imageType] = ($realPath && file_exists($realPath) ? @getimagesize($realPath) : null) ?: [0, 0, null];

        // Jika ekstensi GD tersedia dan mendukung imagewebp, konversi ke WebP
        if (extension_loaded('gd') && function_exists('imagewebp')) {
            $sourceImage = match ($imageType) {
                IMAGETYPE_JPEG => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($realPath) : null,
                IMAGETYPE_PNG => function_exists('imagecreatefrompng') ? @imagecreatefrompng($realPath) : null,
                IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($realPath) : null,
                IMAGETYPE_GIF => function_exists('imagecreatefromgif') ? @imagecreatefromgif($realPath) : null,
                IMAGETYPE_BMP => function_exists('imagecreatefrombmp') ? @imagecreatefrombmp($realPath) : null,
                default => null,
            };

            if ($sourceImage) {
                $filename = Str::random(40) . '.webp';
                $relativePath = trim($directory, '/') . '/' . $filename;

                // Tangani transparansi alpha untuk PNG/WebP
                imagepalettetotruecolor($sourceImage);
                imagealphablending($sourceImage, true);
                imagesavealpha($sourceImage, true);

                // Buffer output WebP
                ob_start();
                imagewebp($sourceImage, null, $quality);
                $webpContent = ob_get_clean();
                imagedestroy($sourceImage);

                if ($webpContent !== false && ! empty($webpContent)) {
                    Storage::disk('public')->put($relativePath, $webpContent);

                    return [
                        'path' => $relativePath,
                        'width' => $width,
                        'height' => $height,
                    ];
                }
            }
        }

        // Fallback jika bukan tipe yang didukung GD atau GD tidak aktif: simpan apa adanya
        $fallbackPath = $file->store($directory, 'public');

        return [
            'path' => $fallbackPath,
            'width' => $width,
            'height' => $height,
        ];
    }
}

