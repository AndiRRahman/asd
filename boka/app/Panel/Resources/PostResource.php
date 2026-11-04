<?php

namespace App\Panel\Resources;

use App\Models\Post;
use App\Panel\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PostResource extends Resource
{
    public static string $model = Post::class;

    public static string $permission = 'konten.kelola';

    public static function title(): string
    {
        return 'Berita & dokumentasi';
    }

    public static function columns(): array
    {
        return [['key' => 'title', 'label' => 'Judul'], ['key' => 'type', 'label' => 'Jenis'], ['key' => 'location', 'label' => 'Lokasi'], ['key' => 'published_at', 'label' => 'Terbit', 'type' => 'datetime']];
    }

    public static function fields(): array
    {
        return [
            ['name' => 'title', 'label' => 'Judul', 'type' => 'translatable'],
            ['name' => 'type', 'label' => 'Jenis', 'type' => 'select', 'options' => [['value' => 'berita', 'label' => 'Berita'], ['value' => 'dokumentasi', 'label' => 'Dokumentasi kegiatan']]],
            ['name' => 'excerpt', 'label' => 'Ringkasan', 'type' => 'translatable', 'multiline' => true],
            ['name' => 'body', 'label' => 'Isi (HTML sederhana: p, strong, em, ul, li, a)', 'type' => 'translatable', 'multiline' => true, 'rows' => 10],
            ['name' => 'cover', 'label' => 'Foto sampul', 'type' => 'image', 'directory' => 'kabar'],
            ['name' => 'location', 'label' => 'Lokasi kegiatan', 'type' => 'text'],
            ['name' => 'published_at', 'label' => 'Waktu terbit (kosongkan untuk draf)', 'type' => 'datetime'],
        ];
    }

    public static function rules(?Model $model): array
    {
        return [
            'title.id' => ['required', 'string', 'max:160'], 'title.en' => ['nullable', 'string', 'max:160'],
            'type' => ['required', 'in:berita,dokumentasi'],
            'excerpt.id' => ['nullable', 'string', 'max:300'], 'excerpt.en' => ['nullable', 'string', 'max:300'],
            'body.id' => ['required', 'string'], 'body.en' => ['nullable', 'string'],
            'cover' => ['nullable', 'image', 'max:10240'], 'location' => ['nullable', 'string', 'max:120'], 'published_at' => ['nullable', 'date'],
        ];
    }

    public static function fill(Model $model, array $data): void
    {
        parent::fill($model, $data);
        $model->slug ??= Str::slug($data['title']['id']).'-'.Str::lower(Str::random(4));
        $model->author_id ??= auth()->id();
    }
}
