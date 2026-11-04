<?php

namespace App\Support;

/**
 * Ilustrasi SVG sementara untuk produk yang belum punya foto: latar warna kategori,
 * motif walasuji (belah ketupat bersusun), ikon kategori, dan nama singkat produk.
 * Begitu Admin mengunggah foto asli, ilustrasi ini tidak dipakai lagi.
 */
final class ProductArt
{
    /** Warna dominan per kode kategori induk. */
    public const PALETTE = [
        'KOP' => ['#5B3A29', '#F3E3CF'],
        'SUT' => ['#7A1E2C', '#F7E1E3'],
        'LAU' => ['#1F5468', '#DDEEF2'],
        'PGN' => ['#A0601A', '#FBEBD3'],
        'KRJ' => ['#6B5B2E', '#F1EBD6'],
        'TAN' => ['#2F6B4F', '#E0EFE6'],
    ];

    /** Glyph sederhana bergaya garis, ketebalan sama dengan Lucide. */
    private const GLYPHS = [
        'KOP' => '<path d="M10 2v2M14 2v2M16 8a1 1 0 0 1 1 1v8a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V9a1 1 0 0 1 1-1h14a4 4 0 1 1 0 8h-1M6 2v2"/>',
        'SUT' => '<path d="M4 4h16v16H4z"/><path d="M4 9h16M4 15h16M9 4v16M15 4v16"/>',
        'LAU' => '<path d="M6.5 12c.94-3.46 4.94-6 8.5-6 3.56 0 6.06 2.54 7 6-.94 3.47-3.44 6-7 6s-7.56-2.53-8.5-6Z"/><path d="M18 12v.5M16 17.93a9.77 9.77 0 0 1 0-11.86M7 10.67C7 8 5.58 5.97 2.73 5.5c-1 1.5-1 5 .23 6.5-1.24 1.5-1.24 5-.23 6.5C5.58 18.03 7 16 7 13.33"/>',
        'PGN' => '<path d="M2 12h20M20 12v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-8M4 12a8 8 0 0 1 16 0"/><path d="M12 4V2"/>',
        'KRJ' => '<path d="M3 7l9-4 9 4-9 4-9-4z"/><path d="M3 7v10l9 4 9-4V7M12 11v10"/>',
        'TAN' => '<path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/><path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"/>',
    ];

    public static function svg(string $title, string $code, string $subtitle = ''): string
    {
        [$ink, $paper] = self::PALETTE[$code] ?? self::PALETTE['KRJ'];
        $glyph = self::GLYPHS[$code] ?? self::GLYPHS['KRJ'];
        $title = htmlspecialchars($title, ENT_XML1);
        $subtitle = htmlspecialchars($subtitle, ENT_XML1);

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="800" height="800" viewBox="0 0 800 800">
  <defs>
    <pattern id="w" width="56" height="56" patternUnits="userSpaceOnUse">
      <path d="M28 2 54 28 28 54 2 28Z M28 14 42 28 28 42 14 28Z" fill="none" stroke="{$ink}" stroke-opacity=".09" stroke-width="2"/>
    </pattern>
  </defs>
  <rect width="800" height="800" fill="{$paper}"/>
  <rect width="800" height="800" fill="url(#w)"/>
  <circle cx="400" cy="330" r="170" fill="{$ink}" fill-opacity=".08"/>
  <g transform="translate(280 210) scale(10)" fill="none" stroke="{$ink}" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">{$glyph}</g>
  <text x="400" y="600" text-anchor="middle" font-family="Plus Jakarta Sans, system-ui, sans-serif" font-size="40" font-weight="700" fill="{$ink}">{$title}</text>
  <text x="400" y="650" text-anchor="middle" font-family="Plus Jakarta Sans, system-ui, sans-serif" font-size="26" fill="{$ink}" fill-opacity=".7">{$subtitle}</text>
</svg>
SVG;
    }
}
