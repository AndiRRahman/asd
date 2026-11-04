<?php

namespace App\Panel\Resources;

use App\Domain\Shipping\BoneDistricts;
use App\Models\Origin;
use App\Panel\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OriginResource extends Resource
{
    public static string $model = Origin::class;

    public static function title(): string
    {
        return 'Titik asal kirim';
    }

    public static function columns(): array
    {
        return [['key' => 'name', 'label' => 'Nama'], ['key' => 'district', 'label' => 'Kecamatan'], ['key' => 'pickup_enabled', 'label' => 'Ambil sendiri', 'type' => 'boolean'], ['key' => 'is_active', 'label' => 'Aktif', 'type' => 'boolean']];
    }

    public static function fields(): array
    {
        return [
            ['name' => 'name', 'label' => 'Nama titik', 'type' => 'text'],
            ['name' => 'address', 'label' => 'Alamat lengkap', 'type' => 'textarea'],
            ['name' => 'district', 'label' => 'Kecamatan', 'type' => 'select', 'options' => collect(BoneDistricts::ALL)->map(fn ($d) => ['value' => $d, 'label' => $d])->all()],
            ['name' => 'destination_id', 'label' => 'ID lokasi RajaOngkir', 'type' => 'text'],
            ['name' => 'contact', 'label' => 'Kontak', 'type' => 'text'],
            ['name' => 'pickup_enabled', 'label' => 'Melayani ambil sendiri', 'type' => 'boolean'],
            ['name' => 'pickup_hours', 'label' => 'Jam layanan ambil', 'type' => 'text'],
            ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'boolean'],
            ['name' => 'local_rates', 'label' => 'Tarif kurir lokal per kecamatan tujuan', 'type' => 'repeater', 'fields' => [
                ['name' => 'destination_district', 'label' => 'Kecamatan tujuan', 'type' => 'select', 'options' => collect(BoneDistricts::ALL)->map(fn ($d) => ['value' => $d, 'label' => $d])->all()],
                ['name' => 'fee', 'label' => 'Tarif (Rp)', 'type' => 'money'],
            ]],
        ];
    }

    public static function toForm(Model $model): array
    {
        return [...parent::toForm($model), 'local_rates' => $model->localRates()->get(['destination_district', 'fee'])->toArray()];
    }

    public static function rules(?Model $model): array
    {
        return [
            'name' => ['required', 'string', 'max:120'], 'address' => ['required', 'string', 'max:300'],
            'district' => ['required', Rule::in(BoneDistricts::ALL)], 'destination_id' => ['nullable', 'string', 'max:40'],
            'contact' => ['nullable', 'string', 'max:60'], 'pickup_enabled' => ['boolean'], 'pickup_hours' => ['nullable', 'string', 'max:120'],
            'is_active' => ['boolean'],
            'local_rates' => ['array'], 'local_rates.*.destination_district' => ['required', Rule::in(BoneDistricts::ALL), 'distinct'], 'local_rates.*.fee' => ['required', 'integer', 'min:0'],
        ];
    }

    public static function afterSave(Model $model, array $data, Request $request): void
    {
        $model->localRates()->delete();
        $model->localRates()->createMany($data['local_rates'] ?? []);
    }

    public static function deletable(Model $model): bool
    {
        return false;
    }
}
