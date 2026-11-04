<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

final class Qr
{
    /** SVG siap disisipkan ke HTML (tanpa deklarasi XML). */
    public static function svg(string $content, int $size = 180): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd)))->writeString($content);

        return trim(preg_replace('/^<\?xml[^>]*\?>/', '', $svg));
    }
}
