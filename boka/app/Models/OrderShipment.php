<?php

namespace App\Models;

use App\Models\Concerns\HasHashId;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Unguarded]
class OrderShipment extends Model
{
    use HasHashId;

    protected function casts(): array
    {
        return ['origin_snapshot' => 'array', 'shipped_at' => 'datetime', 'pickup_locked_until' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'shipment_id');
    }

    /** Paket siap jika sudah punya resi atau siap diambil (bagian 5, K16). */
    public function isDispatched(): bool
    {
        return filled($this->tracking_number) || in_array($this->status, ['siap_diambil', 'diambil', 'terkirim'], true);
    }
}
