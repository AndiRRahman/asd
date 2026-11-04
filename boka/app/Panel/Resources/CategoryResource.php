<?php

namespace App\Panel\Resources;

use App\Models\Category;
use App\Panel\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class CategoryResource extends Resource
{
    public static string $model = Category::class;

    public static function title(): string
    {
        return 'Kategori';
    }

    public static function query(): Builder
    {
        return Category::query()->with('parent')->orderByRaw('COALESCE(parent_id, id), parent_id IS NOT NULL, sort_order');
    }

    public static function columns(): array
    {
        return [['key' => 'name', 'label' => 'Nama'], ['key' => 'parent.name', 'label' => 'Induk'], ['key' => 'code', 'label' => 'Kode SKU'], ['key' => 'sort_order', 'label' => 'Urutan']];
    }

    public static function fields(): array
    {
        return [
            ['name' => 'name', 'label' => 'Nama kategori', 'type' => 'translatable'],
            ['name' => 'parent_id', 'label' => 'Induk (kosongkan untuk kategori utama)', 'type' => 'select', 'options' => Category::query()->whereNull('parent_id')->get()->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])->all()],
            ['name' => 'slug', 'label' => 'Slug URL', 'type' => 'text'],
            ['name' => 'code', 'label' => 'Kode SKU (3 huruf)', 'type' => 'text'],
            ['name' => 'icon', 'label' => 'Ikon (coffee, scroll, fish, cookie, gem, leaf)', 'type' => 'text'],
            ['name' => 'sort_order', 'label' => 'Urutan', 'type' => 'number'],
        ];
    }

    public static function rules(?Model $model): array
    {
        return [
            'name.id' => ['required', 'string', 'max:80'], 'name.en' => ['nullable', 'string', 'max:80'],
            // Dua level saja: induk harus kategori utama.
            'parent_id' => ['nullable', Rule::exists('categories', 'id')->whereNull('parent_id')],
            'slug' => ['required', 'alpha_dash', 'max:100', Rule::unique('categories')->ignore($model)],
            'code' => ['required', 'alpha', 'size:3'], 'icon' => ['nullable', 'string', 'max:40'], 'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public static function deletable(Model $model): bool
    {
        return ! $model->products()->exists() && ! $model->children()->exists();
    }
}
