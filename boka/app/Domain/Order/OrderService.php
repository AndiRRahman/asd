<?php

namespace App\Domain\Order;

use App\Domain\BusinessRuleException;
use App\Domain\Cart\CartService;
use App\Domain\Catalog\StockService;
use App\Domain\Promotion\VoucherService;
use App\Models\Order;
use App\Models\OrderShipment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Aksi pesanan setelah dibuat: kedaluwarsa, batal, kirim, ambil sendiri, selesai (bagian 5 dan 11.1).
 */
class OrderService
{
    public function __construct(
        private OrderStateMachine $machine,
        private StockService $stock,
        private VoucherService $vouchers,
        private CartService $cart,
    ) {}

    /** Dipanggil penjadwal tiap menit dan juga saat halaman pesanan dibuka (jika penjadwal mati). */
    public function expireIfDue(Order $order): bool
    {
        if ($order->status !== OrderStatus::MenungguPembayaran || $order->payment_deadline_at?->isFuture()) {
            return false;
        }

        DB::transaction(function () use ($order) {
            $order = Order::query()->lockForUpdate()->find($order->id);

            if ($order->status !== OrderStatus::MenungguPembayaran) {
                return;
            }

            $this->machine->transition($order, OrderStatus::Kedaluwarsa, note: 'Batas pembayaran lewat');
            $this->releaseReservations($order);
            $this->cart->restoreFromOrder($order);
            $this->blockIfRepeatedExpiry($order->user);
        });

        return true;
    }

    public function cancelByBuyer(Order $order, User $buyer): void
    {
        if ($order->status !== OrderStatus::MenungguPembayaran) {
            throw new BusinessRuleException('Pesanan hanya bisa dibatalkan sebelum bukti transfer diunggah.', 'invalid_transition', 409);
        }

        DB::transaction(function () use ($order, $buyer) {
            $this->machine->transition($order, OrderStatus::Dibatalkan, $buyer, 'Dibatalkan Pembeli');
            $order->update(['cancel_reason' => 'Dibatalkan Pembeli']);
            $this->releaseReservations($order);
        });
    }

    /** Admin membatalkan pesanan Diproses; pengembalian dana dicatat terpisah (OrderRefund). */
    public function cancelByAdmin(Order $order, User $admin, string $reason): void
    {
        DB::transaction(function () use ($order, $admin, $reason) {
            $this->machine->transition($order, OrderStatus::Dibatalkan, $admin, $reason);
            $order->update(['cancel_reason' => $reason]);
            $this->releaseReservations($order);
        });
    }

    /** Mengembalikan stok, kuota voucher, dan kuota flash sale. */
    public function releaseReservations(Order $order): void
    {
        foreach ($order->items()->with('variant.product')->get() as $item) {
            if ($item->variant) {
                $this->stock->adjust($item->variant, 'restore', $item->qty, reference: $order->code);
            }

            if ($item->flash_sale_item_id) {
                DB::table('flash_sale_items')->where('id', $item->flash_sale_item_id)->where('sold', '>=', $item->qty)->decrement('sold', $item->qty);
            }
        }

        $this->vouchers->release($order);
    }

    public function setTracking(OrderShipment $shipment, string $trackingNumber, User $admin): void
    {
        $this->guardShippable($shipment);
        $shipment->update(['tracking_number' => trim($trackingNumber), 'status' => 'dikirim', 'shipped_at' => now()]);
        $this->advanceIfAllDispatched($shipment->order, $admin);
    }

    public function markReadyForPickup(OrderShipment $shipment, User $admin): void
    {
        $this->guardShippable($shipment);

        if ($shipment->method !== 'ambil') {
            throw new BusinessRuleException('Paket ini tidak memakai metode ambil sendiri.');
        }

        $shipment->update(['status' => 'siap_diambil', 'pickup_code_hash' => hash('sha256', self::pickupCode($shipment)), 'shipped_at' => now()]);
        $this->advanceIfAllDispatched($shipment->order, $admin);
    }

    /** Kurir lokal wajib unggah foto serah terima dan nama penerima (11.1 T7). */
    public function markLocalDelivered(OrderShipment $shipment, string $photoPath, string $receiver, User $admin): void
    {
        $this->guardShippable($shipment);
        $shipment->update(['status' => 'terkirim', 'delivery_photo' => $photoPath, 'receiver_name' => $receiver, 'shipped_at' => now()]);
        $this->advanceIfAllDispatched($shipment->order, $admin);
    }

    /** Kode ambil 6 digit (11.1 T6). 5 kali salah mengunci input 15 menit (K29). */
    public function verifyPickup(OrderShipment $shipment, string $code): void
    {
        if ($shipment->pickup_locked_until?->isFuture()) {
            throw new BusinessRuleException('Input kode dikunci sampai '.$shipment->pickup_locked_until->timezone('Asia/Makassar')->format('H:i').' WITA.', 'pickup_locked', 423);
        }

        if (! $shipment->pickup_code_hash || ! hash_equals($shipment->pickup_code_hash, hash('sha256', trim($code)))) {
            $attempts = $shipment->pickup_attempts + 1;
            $lock = $attempts >= config('boka.pickup_max_attempts');
            $shipment->update([
                'pickup_attempts' => $lock ? 0 : $attempts,
                'pickup_locked_until' => $lock ? now()->addMinutes(config('boka.pickup_lock_minutes')) : null,
            ]);

            throw new BusinessRuleException('Kode ambil salah.', 'pickup_code_invalid');
        }

        $shipment->update(['status' => 'diambil', 'pickup_attempts' => 0]);
    }

    /**
     * Kode diturunkan dari APP_KEY sehingga bisa ditampilkan ke Pembeli tanpa disimpan dalam bentuk asli;
     * database hanya menyimpan hash-nya.
     */
    public static function pickupCode(OrderShipment $shipment): string
    {
        $hmac = hash_hmac('sha256', 'pickup:'.$shipment->id.':'.$shipment->order_id, config('app.key'));

        return str_pad((string) (hexdec(substr($hmac, 0, 8)) % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    public function complete(Order $order, ?User $buyer = null): void
    {
        DB::transaction(function () use ($order, $buyer) {
            $this->machine->transition($order, OrderStatus::Selesai, $buyer, $buyer ? 'Pesanan diterima Pembeli' : 'Selesai otomatis 7 hari setelah dikirim');
            $order->update(['completed_at' => now()]);

            foreach ($order->items as $item) {
                DB::table('products')->where('id', $item->product_id)->increment('sold_count', $item->qty);
            }
        });
    }

    private function guardShippable(OrderShipment $shipment): void
    {
        if ($shipment->order->status !== OrderStatus::Diproses) {
            throw new BusinessRuleException('Paket hanya bisa diproses saat pesanan berstatus Diproses.', 'invalid_transition', 409);
        }
    }

    /** Status Dikirim hanya setelah semua paket punya resi atau siap diambil (K16). */
    private function advanceIfAllDispatched(Order $order, User $admin): void
    {
        $order->refresh();

        if ($order->status === OrderStatus::Diproses && $order->shipments->every(fn (OrderShipment $s) => $s->isDispatched())) {
            $this->machine->transition($order, OrderStatus::Dikirim, $admin, 'Semua paket sudah dikirim atau siap diambil');
        }
    }

    /** 3 pesanan kedaluwarsa dalam 7 hari memblokir checkout 24 jam. Pembatalan sendiri tidak dihitung. */
    private function blockIfRepeatedExpiry(User $user): void
    {
        $expired = Order::query()
            ->where('user_id', $user->id)
            ->where('status', OrderStatus::Kedaluwarsa)
            ->where('updated_at', '>=', now()->subDays((int) Setting::get('expired_block_window_days')))
            ->count();

        if ($expired >= (int) Setting::get('expired_block_count')) {
            $user->forceFill(['checkout_blocked_until' => now()->addHours((int) Setting::get('checkout_block_hours'))])->save();
        }
    }
}
