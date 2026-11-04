<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use App\Models\Concerns\HasHashId;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Unguarded]
class ProductVariant extends Model
{
    use Audited, HasHashId;

    protected function casts(): array
    {
        return ['options' => 'array'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'variant_id')->latest('id');
    }

    /** Nama varian yang dibaca manusia, misalnya "250 g · Bubuk". */
    public function label(): ?string
    {
        $options = $this->options ?? [];

        return $options === [] ? null : implode(' · ', array_values($options));
    }

    /** Jumlah maksimal yang boleh dibeli dalam satu pesanan (bagian 4.4 titik 1). */
    public function maxBuyable(): int
    {
        return $this->max_per_order ? min($this->stock, $this->max_per_order) : $this->stock;
    }
}
