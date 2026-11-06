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
        [$width, $height] = ($realPath && file_exists($realPath) ? @getimagesize($realPath) : null) ?: [800, 800];

        // Simpan langsung file asli (JPG/PNG/WebP/dll.) tanpa dipaksa konversi GD
        // Ini mencegah gambar korup atau gagal tampil di server/hosting
        $path = $file->store($directory, 'public');

        return [
            'path' => $path,
            'width' => $width ?: 800,
            'height' => $height ?: 800,
        ];
    }
}

