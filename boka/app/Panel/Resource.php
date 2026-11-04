<?php

namespace App\Panel;

use App\Support\HashId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Definisi modul panel sederhana: kolom tabel, field form, dan aturan validasi.
 * Satu controller (PanelResourceController) dan dua halaman React melayani semua modul turunan.
 *
 * Jenis field: text, textarea, number, money, select, boolean, datetime, translatable, image, repeater, tags.
 */
abstract class Resource
{
    /** @var class-string<Model> */
    public static string $model;

    public static string $permission = 'produk.kelola';

    abstract public static function title(): string;

    /** @return list<array{key: string, label: string, type?: string}> */
    abstract public static function columns(): array;

    /** @return list<array<string, mixed>> */
    abstract public static function fields(): array;

    /** @return array<string, mixed> */
    abstract public static function rules(?Model $model): array;

    public static function slug(): string
    {
        return Str::kebab(Str::beforeLast(class_basename(static::class), 'Resource'));
    }

    public static function query(): Builder
    {
        return static::$model::query()->latest('id');
    }

    public static function row(Model $model): array
    {
        return collect(static::columns())->mapWithKeys(fn ($c) => [$c['key'] => data_get($model, $c['key'])])->put('id', HashId::encode((int) $model->getKey()))->all();
    }

    /** Nilai form saat mengedit. Field translatable dikirim sebagai {id, en}. */
    public static function toForm(Model $model): array
    {
        return collect(static::fields())->mapWithKeys(function ($field) use ($model) {
            $value = $field['type'] === 'translatable' && method_exists($model, 'getTranslations')
                ? ['id' => $model->getTranslation($field['name'], 'id', false), 'en' => $model->getTranslation($field['name'], 'en', false)]
                : $model->{$field['name']};

            if ($field['type'] === 'datetime' && $value) {
                $value = $value->timezone('Asia/Makassar')->format('Y-m-d\TH:i');
            }

            return [$field['name'] => $value];
        })->all();
    }

    /** Data tervalidasi menjadi atribut model. */
    public static function fill(Model $model, array $data): void
    {
        foreach (static::fields() as $field) {
            if (! Arr::has($data, $field['name']) || $field['type'] === 'repeater' || $field['type'] === 'image') {
                continue;
            }

            $value = $data[$field['name']];

            if ($field['type'] === 'translatable') {
                $value = array_filter($value ?? [], fn ($v) => filled($v));
            } elseif ($field['type'] === 'datetime' && $value) {
                $value = Carbon::parse($value, 'Asia/Makassar')->utc();
            }

            $model->{$field['name']} = $value;
        }
    }

    /** Untuk relasi atau berkas yang disimpan setelah model tersimpan. */
    public static function afterSave(Model $model, array $data, Request $request): void
    {
        foreach (static::fields() as $field) {
            if ($field['type'] === 'image' && $request->hasFile($field['name'])) {
                $model->update([$field['name'] => '/storage/'.$request->file($field['name'])->store($field['directory'] ?? 'konten', 'public')]);
            }
        }
    }

    public static function deletable(Model $model): bool
    {
        return true;
    }

    public static function meta(): array
    {
        return ['slug' => static::slug(), 'title' => static::title(), 'columns' => static::columns(), 'fields' => static::fields()];
    }
}
