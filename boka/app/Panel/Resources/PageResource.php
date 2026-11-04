<?php

namespace App\Panel\Resources;

use App\Models\Page;
use App\Panel\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class PageResource extends Resource
{
    public static string $model = Page::class;

    public static string $permission = 'konten.kelola';

    public static function title(): string
    {
        return 'Halaman statis';
    }

    public static function columns(): array
    {
        return [['key' => 'title', 'label' => 'Judul'], ['key' => 'slug', 'label' => 'Alamat']];
    }

    public static function fields(): array
    {
        return [
            ['name' => 'title', 'label' => 'Judul', 'type' => 'translatable'],
            ['name' => 'slug', 'label' => 'Alamat (/halaman/...)', 'type' => 'text'],
            ['name' => 'body', 'label' => 'Isi (HTML sederhana)', 'type' => 'translatable', 'multiline' => true, 'rows' => 12],
        ];
    }

    public static function rules(?Model $model): array
    {
        return [
            'title.id' => ['required', 'string', 'max:160'], 'title.en' => ['nullable', 'string', 'max:160'],
            'slug' => ['required', 'alpha_dash', Rule::unique('pages')->ignore($model)],
            'body.id' => ['required', 'string'], 'body.en' => ['nullable', 'string'],
        ];
    }
}
