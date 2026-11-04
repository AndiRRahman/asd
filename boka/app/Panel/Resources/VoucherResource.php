<?php

namespace App\Panel\Resources;

use App\Models\Voucher;
use App\Panel\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class VoucherResource extends Resource
{
    public static string $model = Voucher::class;

    public static string $permission = 'promosi.kelola';

    public static function title(): string
    {
        return 'Voucher';
    }

    public static function columns(): array
    {
        return [['key' => 'code', 'label' => 'Kode'], ['key' => 'type', 'label' => 'Jenis'], ['key' => 'value', 'label' => 'Nilai'], ['key' => 'used', 'label' => 'Terpakai'], ['key' => 'quota', 'label' => 'Kuota'], ['key' => 'ends_at', 'label' => 'Berakhir', 'type' => 'datetime']];
    }

    public static function fields(): array
    {
        return [
            ['name' => 'code', 'label' => 'Kode voucher', 'type' => 'text'],
            ['name' => 'type', 'label' => 'Jenis', 'type' => 'select', 'options' => [['value' => 'belanja', 'label' => 'Voucher belanja'], ['value' => 'ongkir', 'label' => 'Voucher ongkir']]],
            ['name' => 'discount_type', 'label' => 'Bentuk potongan', 'type' => 'select', 'options' => [['value' => 'persen', 'label' => 'Persen'], ['value' => 'nominal', 'label' => 'Nominal tetap']]],
            ['name' => 'value', 'label' => 'Nilai (persen atau rupiah)', 'type' => 'number'],
            ['name' => 'max_discount', 'label' => 'Potongan maksimal (Rp)', 'type' => 'money'],
            ['name' => 'min_spend', 'label' => 'Minimal belanja (Rp)', 'type' => 'money'],
            ['name' => 'quota', 'label' => 'Kuota total', 'type' => 'number'],
            ['name' => 'per_user_limit', 'label' => 'Kuota per pengguna', 'type' => 'number'],
            ['name' => 'starts_at', 'label' => 'Mulai (WITA)', 'type' => 'datetime'],
            ['name' => 'ends_at', 'label' => 'Berakhir (WITA)', 'type' => 'datetime'],
        ];
    }

    public static function rules(?Model $model): array
    {
        return [
            'code' => ['required', 'alpha_num', 'max:32', Rule::unique('vouchers')->ignore($model)],
            'type' => ['required', 'in:belanja,ongkir'], 'discount_type' => ['required', 'in:persen,nominal'],
            'value' => ['required', 'integer', 'min:1', Rule::when(request('discount_type') === 'persen', 'max:100')],
            'max_discount' => ['nullable', 'integer', 'min:0'], 'min_spend' => ['nullable', 'integer', 'min:0'],
            'quota' => ['nullable', 'integer', 'min:1'], 'per_user_limit' => ['required', 'integer', 'min:1'],
            'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at'],
        ];
    }

    public static function fill(Model $model, array $data): void
    {
        parent::fill($model, [...$data, 'code' => strtoupper($data['code']), 'min_spend' => $data['min_spend'] ?? 0]);
    }

    public static function deletable(Model $model): bool
    {
        return $model->used === 0;
    }
}
