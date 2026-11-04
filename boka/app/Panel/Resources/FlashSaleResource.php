<?php

namespace App\Panel\Resources;

use App\Models\FlashSale;
use App\Models\ProductVariant;
use App\Panel\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class FlashSaleResource extends Resource
{
    public static string $model = FlashSale::class;

    public static string $permission = 'promosi.kelola';

    public static function title(): string
    {
        return 'Flash sale';
    }

    public static function columns(): array
    {
        return [['key' => 'name', 'label' => 'Nama'], ['key' => 'starts_at', 'label' => 'Mulai', 'type' => 'datetime'], ['key' => 'ends_at', 'label' => 'Berakhir', 'type' => 'datetime']];
    }

    public static function fields(): array
    {
        return [
            ['name' => 'name', 'label' => 'Nama flash sale', 'type' => 'text'],
            ['name' => 'starts_at', 'label' => 'Mulai (WITA)', 'type' => 'datetime'],
            ['name' => 'ends_at', 'label' => 'Berakhir (WITA)', 'type' => 'datetime'],
            ['name' => 'items', 'label' => 'Produk flash sale', 'type' => 'repeater', 'fields' => [
                ['name' => 'variant_id', 'label' => 'Varian', 'type' => 'select', 'options' => ProductVariant::query()->with('product')->get()->map(fn ($v) => ['value' => $v->id, 'label' => $v->product->name.($v->label() ? ' · '.$v->label() : '').' (Rp '.number_format($v->price, 0, ',', '.').')'])->all()],
                ['name' => 'sale_price', 'label' => 'Harga flash (Rp)', 'type' => 'money'],
                ['name' => 'quota', 'label' => 'Kuota', 'type' => 'number'],
            ]],
        ];
    }

    public static function toForm(Model $model): array
    {
        return [...parent::toForm($model), 'items' => $model->items()->get(['variant_id', 'sale_price', 'quota'])->toArray()];
    }

    public static function rules(?Model $model): array
    {
        return [
            'name' => ['required', 'string', 'max:120'], 'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at'],
            'items' => ['array'], 'items.*.variant_id' => ['required', 'exists:product_variants,id', 'distinct'],
            'items.*.sale_price' => ['required', 'integer', 'min:1'], 'items.*.quota' => ['required', 'integer', 'min:1'],
        ];
    }

    public static function afterSave(Model $model, array $data, Request $request): void
    {
        $existing = $model->items()->get()->keyBy('variant_id');

        foreach ($data['items'] ?? [] as $item) {
            $model->items()->updateOrCreate(['variant_id' => $item['variant_id']], ['sale_price' => $item['sale_price'], 'quota' => max($item['quota'], $existing[$item['variant_id']]->sold ?? 0)]);
        }

        $model->items()->whereNotIn('variant_id', collect($data['items'] ?? [])->pluck('variant_id'))->where('sold', 0)->delete();
    }
}
