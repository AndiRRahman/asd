<?php

namespace App\Domain\Cart;

use App\Domain\BusinessRuleException;
use App\Domain\Catalog\ProductStatus;
use App\Domain\Promotion\PriceResolver;
use App\Models\Cart;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Keranjang Pembeli tersimpan di database; keranjang Tamu berupa daftar mentah dari cookie browser
 * (list of {variant_id, qty, price}). Keduanya diperiksa dengan aturan yang sama (bagian 4.4).
 */
class CartService
{
    public function __construct(private PriceResolver $prices) {}

    /** @return list<array{variant_id: int, qty: int, price: int}> */
    public function rawItems(?User $user, array $guest = []): array
    {
        if (! $user) {
            return $this->sanitizeGuest($guest);
        }

        return Cart::query()->firstOrCreate(['user_id' => $user->id])
            ->items()
            ->orderBy('id')
            ->get()
            ->map(fn ($i) => ['variant_id' => $i->variant_id, 'qty' => $i->qty, 'price' => (int) $i->price_when_added])
            ->all();
    }

    /**
     * Baris keranjang dengan harga terkini dan penanda masalah stok.
     *
     * @return Collection<int, array{variant: ProductVariant, qty: int, unit_price: int, compare_price: ?int, flash_sale_item_id: ?int, price_changed: bool, issue: ?string, available: int}>
     */
    public function lines(array $raw): Collection
    {
        $variants = ProductVariant::query()
            ->whereIn('id', array_column($raw, 'variant_id'))
            ->with(['product' => fn ($q) => $q->with(['cover', 'producer', 'origin', 'category'])])
            ->get()
            ->keyBy('id');

        return collect($raw)
            ->filter(fn ($row) => $variants->has($row['variant_id']))
            ->map(function ($row) use ($variants) {
                $variant = $variants[$row['variant_id']];
                $price = $this->prices->resolve($variant);
                $available = $variant->product->status === ProductStatus::Aktif ? $variant->maxBuyable() : 0;

                return [
                    'variant' => $variant,
                    'qty' => (int) $row['qty'],
                    'unit_price' => $price['price'],
                    'compare_price' => $price['compare_price'],
                    'flash_sale_item_id' => $price['flash_sale_item']?->id,
                    'price_changed' => (int) $row['price'] !== $price['price'],
                    'issue' => match (true) {
                        $available === 0 => 'habis',
                        $row['qty'] > $available => 'sisa',
                        default => null,
                    },
                    'available' => $available,
                ];
            })
            ->values();
    }

    /**
     * Titik 2: jumlah di keranjang + jumlah baru tidak boleh melebihi stok tersedia. Ditolak, bukan dipotong diam-diam.
     *
     * @return array guest cart baru (kosong untuk Pembeli)
     */
    public function add(?User $user, array $guest, int $variantId, int $qty): array
    {
        $variant = ProductVariant::query()->with('product')->findOrFail($variantId);

        if ($variant->product->status !== ProductStatus::Aktif) {
            throw new BusinessRuleException('Produk ini sedang tidak tersedia.', 'product_unavailable', 409);
        }

        $raw = $this->rawItems($user, $guest);
        $inCart = (int) (collect($raw)->firstWhere('variant_id', $variantId)['qty'] ?? 0);
        $available = $variant->maxBuyable();

        if ($qty < 1 || $inCart + $qty > $available) {
            $canAdd = max(0, $available - $inCart);
            $message = $available === 0
                ? 'Stok produk ini habis.'
                : "Stok tersisa {$available}, di keranjang Anda sudah ada {$inCart}. Anda bisa menambah {$canAdd} lagi.";

            throw new BusinessRuleException($message, 'insufficient_stock', 409, [
                'items' => [['variant_id' => $variantId, 'requested' => $inCart + $qty, 'available' => $available]],
                'can_add' => $canAdd,
            ]);
        }

        return $this->setQty($user, $guest, $variantId, $inCart + $qty);
    }

    public function update(?User $user, array $guest, int $variantId, int $qty): array
    {
        if ($qty < 1) {
            return $this->remove($user, $guest, $variantId);
        }

        $variant = ProductVariant::query()->findOrFail($variantId);

        if ($qty > $variant->maxBuyable()) {
            throw new BusinessRuleException("Stok tersisa {$variant->maxBuyable()}.", 'insufficient_stock', 409, [
                'items' => [['variant_id' => $variantId, 'requested' => $qty, 'available' => $variant->maxBuyable()]],
            ]);
        }

        return $this->setQty($user, $guest, $variantId, $qty);
    }

    public function remove(?User $user, array $guest, int $variantId): array
    {
        if ($user) {
            Cart::query()->firstOrCreate(['user_id' => $user->id])->items()->where('variant_id', $variantId)->delete();

            return [];
        }

        return array_values(array_filter($this->sanitizeGuest($guest), fn ($r) => $r['variant_id'] !== $variantId));
    }

    /** Tombol "Sesuaikan ke stok tersedia". */
    public function adjustToStock(?User $user, array $guest): array
    {
        foreach ($this->lines($this->rawItems($user, $guest)) as $line) {
            if ($line['issue'] !== null) {
                $guest = $line['available'] === 0
                    ? $this->remove($user, $guest, $line['variant']->id)
                    : $this->setQty($user, $guest, $line['variant']->id, $line['available']);
            }
        }

        return $guest;
    }

    /** Menggabungkan keranjang Tamu ke akun saat login. Kelebihan stok ditandai di halaman keranjang. */
    public function mergeGuest(User $user, array $guest): void
    {
        $existing = collect($this->rawItems($user))->keyBy('variant_id');

        foreach ($this->sanitizeGuest($guest) as $row) {
            if (! ProductVariant::query()->whereKey($row['variant_id'])->exists()) {
                continue;
            }

            $this->setQty($user, [], $row['variant_id'], ($existing[$row['variant_id']]['qty'] ?? 0) + $row['qty'], $row['price']);
        }
    }

    /** Item pesanan kedaluwarsa kembali ke keranjang dengan harga terkini (bagian 5). */
    public function restoreFromOrder(Order $order): void
    {
        $existing = collect($this->rawItems($order->user))->keyBy('variant_id');

        foreach ($order->items as $item) {
            if ($item->variant) {
                $this->setQty($order->user, [], $item->variant_id, ($existing[$item->variant_id]['qty'] ?? 0) + $item->qty);
            }
        }
    }

    public function clear(User $user): void
    {
        Cart::query()->where('user_id', $user->id)->first()?->items()->delete();
    }

    public function count(?User $user, array $guest = []): int
    {
        return (int) collect($this->rawItems($user, $guest))->sum('qty');
    }

    private function setQty(?User $user, array $guest, int $variantId, int $qty, ?int $priceWhenAdded = null): array
    {
        $variant = ProductVariant::query()->findOrFail($variantId);
        $price = $priceWhenAdded ?? $this->prices->price($variant);

        if ($user) {
            Cart::query()->firstOrCreate(['user_id' => $user->id])->items()->updateOrCreate(
                ['variant_id' => $variantId],
                ['qty' => $qty, 'price_when_added' => $price],
            );

            return [];
        }

        $guest = collect($this->sanitizeGuest($guest))->keyBy('variant_id');
        $guest[$variantId] = ['variant_id' => $variantId, 'qty' => $qty, 'price' => $guest[$variantId]['price'] ?? $price];

        return $guest->values()->all();
    }

    private function sanitizeGuest(array $guest): array
    {
        return collect($guest)
            ->filter(fn ($r) => is_array($r) && isset($r['variant_id'], $r['qty']) && (int) $r['qty'] > 0)
            ->map(fn ($r) => ['variant_id' => (int) $r['variant_id'], 'qty' => min((int) $r['qty'], 9999), 'price' => (int) ($r['price'] ?? 0)])
            ->unique('variant_id')
            ->take(100)
            ->values()
            ->all();
    }
}
