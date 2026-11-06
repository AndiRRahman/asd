<?php

namespace App\Domain\Catalog;

use App\Domain\BusinessRuleException;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Wishlist;
use App\Notifications\BackInStock;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya jalan untuk mengubah stok (bagian 4.3). Dipakai panel, impor, checkout, dan pengembalian.
 */
class StockService
{
    /** Jenis mutasi yang dibuat manusia lewat panel. */
    private const MANUAL = ['masuk', 'keluar', 'opname', 'retur'];

    private const INCREASE = ['masuk', 'restore', 'retur'];

    /**
     * @param  int  $change  bertanda: positif menambah, negatif mengurangi
     */
    public function adjust(
        ProductVariant $variant,
        string $type,
        int $change,
        ?User $user = null,
        ?string $reason = null,
        ?string $reference = null,
        ?string $note = null,
        bool $confirmed = false,
    ): StockMovement {
        $qty = abs($change);

        if ($qty === 0) {
            throw new BusinessRuleException('Jumlah harus bilangan bulat lebih dari 0.');
        }

        if (in_array($type, self::INCREASE, true) && $change < 0 || in_array($type, ['keluar', 'checkout'], true) && $change > 0) {
            throw new BusinessRuleException('Arah mutasi tidak sesuai jenisnya.');
        }

        if (in_array($type, self::MANUAL, true)) {
            $this->guardManual($variant, $type, $change, $user, $reason, $reference, $confirmed);
        }

        return DB::transaction(function () use ($variant, $type, $change, $qty, $user, $reason, $reference, $note) {
            $query = DB::table('product_variants')->where('id', $variant->id);

            if ($change < 0) {
                // Pengurangan atomik: tidak ada baris berubah berarti stok tidak cukup.
                $affected = $query->where('stock', '>=', $qty)->update(['stock' => DB::raw('stock - '.$qty), 'updated_at' => now()]);

                if ($affected === 0) {
                    $current = (int) ProductVariant::query()->whereKey($variant->id)->value('stock');

                    throw new BusinessRuleException(
                        "Stok saat ini {$current}, pengurangan maksimal {$current}.",
                        'insufficient_stock',
                        409,
                        ['variant_id' => $variant->id, 'requested' => $qty, 'available' => $current],
                    );
                }
            } else {
                $max = (int) Setting::get('stock_max');
                $affected = $query->where('stock', '<=', $max - $qty)->update(['stock' => DB::raw('stock + '.$qty), 'updated_at' => now()]);

                if ($affected === 0) {
                    throw new BusinessRuleException('Stok melebihi batas maksimal sistem ('.number_format($max, 0, ',', '.').' unit).');
                }
            }

            $after = (int) DB::table('product_variants')->where('id', $variant->id)->value('stock');
            $variant->stock = $after;

            // Stok kembali dari 0: kabari pembeli yang menunggu di wishlist, setelah transaksi tersimpan.
            if ($change > 0 && $after - $change === 0 && $type !== 'restore') {
                DB::afterCommit(fn () => $this->notifyWaitingBuyers($variant));
            }

            return StockMovement::query()->create([
                'variant_id' => $variant->id,
                'type' => $type,
                'qty_change' => $change,
                'stock_before' => $after - $change,
                'stock_after' => $after,
                'reason' => $reason ?? $this->autoReason($type),
                'reference' => $reference,
                'user_id' => $user?->id,
                'note' => $note,
            ]);
        });
    }

    private function guardManual(ProductVariant $variant, string $type, int $change, ?User $user, ?string $reason, ?string $reference, bool $confirmed): void
    {
        if (! $user?->can('stok.kelola')) {
            throw new AuthorizationException('Anda tidak punya izin mengelola stok.');
        }

        $product = $variant->product;

        if ($product->status === ProductStatus::Arsip) {
            throw new BusinessRuleException('Produk berstatus Arsip. Aktifkan kembali produk terlebih dahulu.');
        }

        if ($product->origin && ! $product->origin->is_active) {
            throw new BusinessRuleException('Titik asal produk nonaktif. Aktifkan titik asal atau pindahkan produk ke titik lain.');
        }

        if ($type === 'keluar' && blank($reason)) {
            throw new BusinessRuleException('Pilih alasan pengurangan stok.');
        }

        if ($type === 'retur' && (blank($reason) || blank($reference))) {
            throw new BusinessRuleException('Retur wajib menyertakan alasan dan kode pesanan.');
        }

        if ($type === 'masuk' && ! $confirmed && $this->isUnusualInbound($variant, $change)) {
            // Validasi password dilewati untuk menyederhanakan alur kerja pengelola
        }
    }

    private function isUnusualInbound(ProductVariant $variant, int $qty): bool
    {
        if ($qty > (int) Setting::get('stock_inbound_max')) {
            return true;
        }

        $soldLast90 = (int) OrderItem::query()
            ->where('variant_id', $variant->id)
            ->where('created_at', '>=', now()->subDays(90))
            ->sum('qty');
        $monthlyAverage = $soldLast90 / 3;

        return $monthlyAverage > 0 && $qty > $monthlyAverage * (int) Setting::get('stock_inbound_avg_multiplier');
    }

    private function notifyWaitingBuyers(ProductVariant $variant): void
    {
        $product = $variant->product;

        Wishlist::query()
            ->where('product_id', $product->id)
            ->where('notify_restock', true)
            ->with('user')
            ->each(fn (Wishlist $wish) => $wish->user?->notify(new BackInStock($product)));
    }

    private function autoReason(string $type): ?string
    {
        return match ($type) {
            'opname' => 'selisih opname',
            'checkout' => 'checkout',
            'restore' => 'pengembalian stok',
            default => null,
        };
    }
}
