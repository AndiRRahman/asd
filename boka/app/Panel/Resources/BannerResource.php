<?php

namespace App\Panel\Resources;

use App\Models\Banner;
use App\Panel\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BannerResource extends Resource
{
    public static string $model = Banner::class;

    public static string $permission = 'promosi.kelola';

    public static function title(): string
    {
        return 'Banner';
    }

    public static function query(): Builder
    {
        return Banner::query()->orderBy('position')->orderBy('sort_order');
    }

    public static function columns(): array
    {
        return [['key' => 'title', 'label' => 'Judul'], ['key' => 'position', 'label' => 'Posisi'], ['key' => 'sort_order', 'label' => 'Urutan'], ['key' => 'ends_at', 'label' => 'Tayang sampai', 'type' => 'datetime']];
    }

    public static function fields(): array
    {
        return [
            ['name' => 'title', 'label' => 'Judul', 'type' => 'translatable'],
            ['name' => 'subtitle', 'label' => 'Keterangan', 'type' => 'translatable', 'multiline' => true],
            ['name' => 'image_desktop', 'label' => 'Gambar desktop', 'type' => 'image', 'directory' => 'banner'],
            ['name' => 'image_mobile', 'label' => 'Gambar HP', 'type' => 'image', 'directory' => 'banner'],
            ['name' => 'link', 'label' => 'Tautan tujuan', 'type' => 'text'],
            ['name' => 'position', 'label' => 'Posisi', 'type' => 'select', 'options' => [['value' => 'hero', 'label' => 'Hero beranda'], ['value' => 'tengah', 'label' => 'Tengah beranda']]],
            ['name' => 'starts_at', 'label' => 'Mulai tayang (WITA)', 'type' => 'datetime'],
            ['name' => 'ends_at', 'label' => 'Selesai tayang (WITA)', 'type' => 'datetime'],
            ['name' => 'sort_order', 'label' => 'Urutan', 'type' => 'number'],
        ];
    }

    public static function rules(?Model $model): array
    {
        return [
            'title.id' => ['required', 'string', 'max:120'], 'title.en' => ['nullable', 'string', 'max:120'],
            'subtitle.id' => ['nullable', 'string', 'max:240'], 'subtitle.en' => ['nullable', 'string', 'max:240'],
            'image_desktop' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'image_mobile' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'link' => ['nullable', 'string', 'max:300'], 'position' => ['required', 'in:hero,tengah'],
            'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after:starts_at'], 'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
