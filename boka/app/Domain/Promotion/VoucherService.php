<?php

namespace App\Domain\Promotion;

use App\Domain\BusinessRuleException;
use App\Domain\Order\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherUsage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Aturan voucher belanja dan voucher ongkir (bagian 6).
 */
class VoucherService
{
    /**
     * @param  Collection<int, array>  $lines  baris keranjang dengan kunci variant, qty, unit_price
     * @param  int  $base  S untuk voucher belanja, O untuk voucher ongkir
     * @return array{voucher: Voucher, discount: int}
     */
    public function evaluate(string $code, string $type, User $user, Collection $lines, int $subtotal, int $base): array
    {
        $voucher = Voucher::query()->where('code', strtoupper(trim($code)))->first();

        if (! $voucher || $voucher->type !== $type) {
            throw new BusinessRuleException($type === 'ongkir' ? 'Voucher ongkir tidak ditemukan.' : 'Voucher belanja tidak ditemukan.', 'voucher_invalid');
        }

        if (now()->lt($voucher->starts_at) || now()->gte($voucher->ends_at)) {
            throw new BusinessRuleException("Voucher {$voucher->code} sedang tidak berlaku.", 'voucher_invalid');
        }

        if ($voucher->quota !== null && $voucher->used >= $voucher->quota) {
            throw new BusinessRuleException("Kuota voucher {$voucher->code} sudah habis.", 'voucher_invalid');
        }

        if ($subtotal < $voucher->min_spend) {
            throw new BusinessRuleException('Minimal belanja untuk voucher ini Rp '.number_format($voucher->min_spend, 0, ',', '.').'.', 'voucher_invalid');
        }

        $usedByUser = VoucherUsage::query()
            ->where('voucher_id', $voucher->id)
            ->where('user_id', $user->id)
            ->whereIn('status', ['dipesan', 'dipakai'])
            ->count();

        if ($usedByUser >= $voucher->per_user_limit) {
            throw new BusinessRuleException('Anda sudah memakai voucher ini.', 'voucher_invalid');
        }

        $scope = $voucher->scope ?? [];

        if (($scope['new_buyer_only'] ?? false) && $this->hasPurchased($user)) {
            throw new BusinessRuleException('Voucher ini khusus pembeli baru.', 'voucher_invalid');
        }

        if ($type === 'belanja' && (! empty($scope['category_ids']) || ! empty($scope['product_ids']))) {
            $base = (int) $lines
                ->filter(fn ($line) => in_array($line['variant']->product->category_id, $scope['category_ids'] ?? [], true)
                    || in_array($line['variant']->product_id, $scope['product_ids'] ?? [], true))
                ->sum(fn ($line) => $line['unit_price'] * $line['qty']);

            if ($base === 0) {
                throw new BusinessRuleException('Voucher ini tidak berlaku untuk produk di keranjang Anda.', 'voucher_invalid');
            }
        }

        return ['voucher' => $voucher, 'discount' => $this->discount($voucher, $base)];
    }

    /** Diskon persen dibulatkan ke bawah; tidak pernah melebihi dasar perhitungan. */
    public function discount(Voucher $voucher, int $base): int
    {
        $amount = $voucher->discount_type === 'persen'
            ? intdiv($base * (int) $voucher->value, 100)
            : (int) $voucher->value;

        if ($voucher->max_discount !== null) {
            $amount = min($amount, (int) $voucher->max_discount);
        }

        return max(0, min($amount, $base));
    }

    /** Memesan kuota secara atomik saat pesanan dibuat. */
    public function reserve(Voucher $voucher, User $user, Order $order): void
    {
        $affected = DB::table('vouchers')
            ->where('id', $voucher->id)
            ->where(fn ($q) => $q->whereNull('quota')->orWhereColumn('used', '<', 'quota'))
            ->increment('used');

        if ($affected === 0) {
            throw new BusinessRuleException("Kuota voucher {$voucher->code} baru saja habis.", 'voucher_invalid', 409);
        }

        VoucherUsage::query()->create(['voucher_id' => $voucher->id, 'user_id' => $user->id, 'order_id' => $order->id]);
    }

    /** Mengembalikan kuota saat pesanan kedaluwarsa atau dibatalkan. */
    public function release(Order $order): void
    {
        foreach ($order->voucherUsages()->where('status', 'dipesan')->get() as $usage) {
            DB::table('vouchers')->where('id', $usage->voucher_id)->where('used', '>', 0)->decrement('used');
            $usage->update(['status' => 'dikembalikan']);
        }
    }

    public function markUsed(Order $order): void
    {
        $order->voucherUsages()->where('status', 'dipesan')->update(['status' => 'dipakai']);
    }

    private function hasPurchased(User $user): bool
    {
        return Order::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [OrderStatus::Diproses, OrderStatus::Dikirim, OrderStatus::Selesai])
            ->exists();
    }
}
