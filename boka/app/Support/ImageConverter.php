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
        [$width, $height, $imageType] = getimagesize($realPath) ?: [0, 0, null];

        // Buat resource gambar GD berdasarkan tipe asli
        $sourceImage = match ($imageType) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($realPath),
            IMAGETYPE_PNG => @imagecreatefrompng($realPath),
            IMAGETYPE_WEBP => @imagecreatefromwebp($realPath),
            IMAGETYPE_GIF => @imagecreatefromgif($realPath),
            IMAGETYPE_BMP => @imagecreatefrombmp($realPath),
            default => null,
        };

        $filename = Str::random(40) . '.webp';
        $relativePath = trim($directory, '/') . '/' . $filename;

        // Jika GD berhasil memuat gambar dan fungsi imagewebp tersedia
        if ($sourceImage && function_exists('imagewebp')) {
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

        // Fallback jika bukan tipe yang didukung GD: simpan apa adanya
        $fallbackPath = $file->store($directory, 'public');

        return [
            'path' => $fallbackPath,
            'width' => $width,
            'height' => $height,
        ];
    }
}

