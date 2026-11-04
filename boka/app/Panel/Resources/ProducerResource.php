<?php

namespace App\Panel\Resources;

use App\Domain\Shipping\BoneDistricts;
use App\Models\Origin;
use App\Models\Producer;
use App\Panel\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProducerResource extends Resource
{
    public static string $model = Producer::class;

    public static function title(): string
    {
        return 'Produsen';
    }

    public static function columns(): array
    {
        return [['key' => 'name', 'label' => 'Nama'], ['key' => 'type', 'label' => 'Jenis'], ['key' => 'village', 'label' => 'Desa'], ['key' => 'district', 'label' => 'Kecamatan']];
    }

    public static function fields(): array
    {
        return [
            ['name' => 'name', 'label' => 'Nama Karang Taruna / UMKM', 'type' => 'text'],
            ['name' => 'type', 'label' => 'Jenis', 'type' => 'select', 'options' => [['value' => 'karang_taruna', 'label' => 'Karang Taruna'], ['value' => 'umkm', 'label' => 'UMKM']]],
            ['name' => 'village', 'label' => 'Desa/Kelurahan', 'type' => 'text'],
            ['name' => 'district', 'label' => 'Kecamatan', 'type' => 'select', 'options' => collect(BoneDistricts::ALL)->map(fn ($d) => ['value' => $d, 'label' => $d])->all()],
            ['name' => 'story', 'label' => 'Cerita produsen', 'type' => 'translatable', 'multiline' => true],
            ['name' => 'contact', 'label' => 'Kontak', 'type' => 'text'],
            ['name' => 'default_origin_id', 'label' => 'Titik asal kirim bawaan', 'type' => 'select', 'options' => Origin::query()->get()->map(fn ($o) => ['value' => $o->id, 'label' => $o->name])->all()],
            ['name' => 'photo', 'label' => 'Foto produsen', 'type' => 'image', 'directory' => 'produsen'],
        ];
    }

    public static function rules(?Model $model): array
    {
        return [
            'name' => ['required', 'string', 'max:120'], 'type' => ['required', 'in:karang_taruna,umkm'],
            'village' => ['required', 'string', 'max:80'], 'district' => ['required', Rule::in(BoneDistricts::ALL)],
            'story.id' => ['nullable', 'string', 'max:2000'], 'story.en' => ['nullable', 'string', 'max:2000'],
            'contact' => ['nullable', 'string', 'max:60'], 'default_origin_id' => ['required', 'exists:origins,id'],
            'photo' => ['nullable', 'image', 'max:10240'],
        ];
    }

    public static function fill(Model $model, array $data): void
    {
        parent::fill($model, $data);
        $model->slug ??= Str::slug($data['name']).'-'.Str::lower(Str::random(4));
    }

    public static function deletable(Model $model): bool
    {
        return ! $model->products()->exists();
    }
}
