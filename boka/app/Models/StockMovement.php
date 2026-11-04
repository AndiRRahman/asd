<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Riwayat mutasi tidak bisa diubah atau dihapus (bagian 4.3).
 */
#[Unguarded]
class StockMovement extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Mutasi stok tidak bisa diubah.'));
        static::deleting(fn () => throw new LogicException('Mutasi stok tidak bisa dihapus.'));
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
