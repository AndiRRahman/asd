<?php

namespace App\Support;

use App\Domain\Order\OrderService;
use App\Domain\Order\OrderStatus;
use App\Domain\Promotion\PriceResolver;
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderShipment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use Illuminate\Support\Collection;

/**
 * Bentuk data yang dikirim ke React dan ke API v1. Satu tempat, agar web dan mobile menerima isi yang sama.
 */
final class Present
{
    public static function productCard(Product $product): array
    {
        $prices = app(PriceResolver::class);
        $product->loadMissing(['variants', 'cover', 'producer']);
        $best = $product->variants->map(fn (ProductVariant $v) => $prices->resolve($v))->sortBy('price')->first();

        return [
            'id' => $product->hashid,
            'name' => $product->name,
            'slug' => $product->slug,
            'image' => $product->cover?->toFront(),
            'price' => $best['price'] ?? 0,
            'compare_price' => $best['compare_price'] ?? null,
            'is_flash' => ($best['flash_sale_item'] ?? null) !== null,
            'producer' => $product->producer ? ['name' => $product->producer->name, 'slug' => $product->producer->slug, 'type' => $product->producer->type, 'district' => $product->producer->district] : null,
            'rating' => $product->rating_count ? round($product->rating_avg, 1) : null,
            'rating_count' => $product->rating_count,
            'sold' => $product->sold_count,
            'in_stock' => $product->variants->sum('stock') > 0,
        ];
    }

    /** @param  iterable<Product>  $products */
    public static function productCards(iterable $products): array
    {
        return collect($products)->map(fn ($p) => self::productCard($p))->values()->all();
    }

    public static function productDetail(Product $product): array
    {
        $prices = app(PriceResolver::class);
        $product->loadMissing(['variants', 'images', 'producer', 'origin', 'category.parent', 'tags']);

        return [
            ...self::productCard($product),
            'description' => $product->description,
            'brewing_instructions' => $product->brewing_instructions,
            'images' => $product->images->map->toFront()->all(),
            'category' => $product->category ? ['name' => $product->category->name, 'slug' => $product->category->slug, 'parent' => $product->category->parent ? ['name' => $product->category->parent->name, 'slug' => $product->category->parent->slug] : null] : null,
            'tags' => $product->tags->pluck('name'),
            'origin' => $product->origin ? ['name' => $product->origin->name, 'district' => $product->origin->district, 'pickup' => $product->origin->pickup_enabled] : null,
            'producer' => $product->producer ? [
                'name' => $product->producer->name,
                'slug' => $product->producer->slug,
                'type' => $product->producer->type,
                'village' => $product->producer->village,
                'district' => $product->producer->district,
                'story' => $product->producer->story,
            ] : null,
            'variants' => $product->variants->map(function (ProductVariant $v) use ($prices) {
                $price = $prices->resolve($v);

                return [
                    'id' => $v->hashid,
                    'label' => $v->label() ?? 'Standar',
                    'options' => $v->options,
                    'price' => $price['price'],
                    'compare_price' => $price['compare_price'],
                    'flash_ends_at' => $price['flash_ends_at'],
                    // Angka pasti hanya jika 10 atau kurang (bagian 4.4).
                    'stock_exact' => $v->stock <= 10 ? $v->stock : null,
                    'max_buyable' => $v->maxBuyable(),
                    'weight_gram' => $v->weight_gram,
                ];
            })->values()->all(),
        ];
    }

    public static function review(Review $review): array
    {
        return [
            'id' => $review->hashid,
            'rating' => $review->rating,
            'body' => $review->body,
            'reply' => $review->reply,
            'author' => self::maskName($review->user?->name ?? 'Pembeli'),
            'product' => $review->relationLoaded('product') && $review->product ? ['name' => $review->product->name, 'slug' => $review->product->slug] : null,
            'date' => $review->created_at?->toIso8601String(),
        ];
    }

    /** @param  Collection<int, array>  $lines */
    public static function cartLines(Collection $lines): array
    {
        return $lines->map(fn ($line) => [
            'variant_id' => $line['variant']->hashid,
            'product' => [
                'name' => $line['variant']->product->name,
                'slug' => $line['variant']->product->slug,
                'image' => $line['variant']->product->cover?->toFront(),
                'producer' => $line['variant']->product->producer?->name,
                'origin' => $line['variant']->product->origin?->name,
            ],
            'variant_label' => $line['variant']->label(),
            'qty' => $line['qty'],
            'unit_price' => $line['unit_price'],
            'compare_price' => $line['compare_price'],
            'line_total' => $line['unit_price'] * $line['qty'],
            'price_changed' => $line['price_changed'],
            'issue' => $line['issue'],
            'available' => $line['available'],
        ])->values()->all();
    }

    public static function quote(array $quote): array
    {
        return [
            'packages' => collect($quote['packages'])->map(fn ($p) => [
                'number' => $p['number'],
                'origin' => ['id' => $p['origin']->hashid, 'name' => $p['origin']->name, 'district' => $p['origin']->district, 'address' => $p['origin']->address, 'pickup_hours' => $p['origin']->pickup_hours],
                'weight_gram' => $p['weight_gram'],
                'items' => self::cartLines($p['lines']),
                'options' => $p['options'],
                'selected' => $p['selected']['key'] ?? null,
            ])->all(),
            'subtotal' => $quote['subtotal'],
            'voucher_discount' => $quote['voucher_discount'],
            'shipping_cost' => $quote['shipping_cost'],
            'shipping_discount' => $quote['shipping_discount'],
            'service_fee' => $quote['service_fee'],
            'grand_total' => $quote['grand_total'],
            'voucher_errors' => $quote['voucher_errors'],
            'expedition_unavailable' => $quote['expedition_unavailable'],
            'has_flash_sale' => $quote['has_flash_sale'],
        ];
    }

    public static function orderSummary(Order $order): array
    {
        $order->loadMissing('items');

        return [
            'code' => $order->code,
            'status' => $order->status->value,
            'grand_total' => $order->grand_total,
            'item_count' => $order->items->sum('qty'),
            'first_item' => $order->items->first()?->product_name,
            'payment_deadline_at' => $order->payment_deadline_at?->toIso8601String(),
            'created_at' => $order->created_at->toIso8601String(),
        ];
    }

    public static function orderDetail(Order $order, bool $forBuyer = true): array
    {
        $order->loadMissing(['items.review', 'shipments.items', 'histories', 'latestProof']);
        $awaitingPayment = $order->status === OrderStatus::MenungguPembayaran;

        return [
            ...self::orderSummary($order),
            'subtotal' => $order->subtotal,
            'voucher_discount' => $order->voucher_discount,
            'shipping_cost' => $order->shipping_cost,
            'shipping_discount' => $order->shipping_discount,
            'service_fee' => $order->service_fee,
            'unique_code' => $order->unique_code,
            'rejection_count' => $order->rejection_count,
            // Nomor rekening hanya tampil di halaman pesanan dan email resmi (11.1 T5).
            'bank' => $order->bank_snapshot,
            'address' => $order->address_snapshot,
            'cancel_reason' => $order->cancel_reason,
            'proof' => $order->latestProof ? [
                'status' => $order->latestProof->status,
                'reject_reason' => $order->latestProof->reject_reason,
                'sender_bank' => $order->latestProof->sender_bank,
                'sender_name' => $order->latestProof->sender_name,
                'uploaded_at' => $order->latestProof->created_at->toIso8601String(),
            ] : null,
            'shipments' => $order->shipments->map(fn (OrderShipment $s, $i) => [
                'id' => $s->hashid,
                'number' => $i + 1,
                'origin' => $s->origin_snapshot,
                'method' => $s->method,
                'courier' => $s->courier,
                'service' => $s->service,
                'cost' => $s->cost,
                'weight_gram' => $s->weight_gram,
                'tracking_number' => $s->tracking_number,
                'status' => $s->status,
                'receiver_name' => $s->receiver_name,
                'shipped_at' => $s->shipped_at?->toIso8601String(),
                'pickup_code' => $forBuyer && $s->method === 'ambil' && $s->status === 'siap_diambil' ? OrderService::pickupCode($s) : null,
                'item_ids' => $s->items->map->hashid,
            ])->all(),
            'items' => $order->items->map(fn ($item) => [
                'id' => $item->hashid,
                'product_id' => HashId::encode($item->product_id),
                'product_name' => $item->product_name,
                'variant_name' => $item->variant_name,
                'unit_price' => $item->unit_price,
                'qty' => $item->qty,
                'shipment_id' => $item->shipment_id ? HashId::encode($item->shipment_id) : null,
                'reviewed' => $item->review !== null,
            ])->all(),
            'histories' => $order->histories->map(fn ($h) => [
                'to' => $h->to_status,
                'note' => $h->note,
                'at' => $h->created_at?->toIso8601String(),
            ])->all(),
            'can_upload_proof' => $awaitingPayment && $order->payment_deadline_at?->isFuture(),
            'can_cancel' => $awaitingPayment,
            'can_complete' => $order->status === OrderStatus::Dikirim,
            'can_review' => $order->status === OrderStatus::Selesai,
        ];
    }

    /** Alamat untuk browser/API: ID disamarkan, user_id tidak dikirim. */
    public static function address(Address $address): array
    {
        return ['id' => $address->hashid, ...$address->only(['label', 'recipient', 'phone', 'destination_id', 'district', 'destination_label', 'full_address', 'postal_code', 'is_default'])];
    }

    /** "Rahmawati" menjadi "R*******i" untuk ulasan publik. */
    public static function maskName(string $name): string
    {
        $first = explode(' ', trim($name))[0];

        return mb_strlen($first) <= 2 ? $first : mb_substr($first, 0, 1).str_repeat('*', mb_strlen($first) - 2).mb_substr($first, -1);
    }

    /** 081234564567 menjadi 0812****4567 (4.6). */
    public static function maskPhone(?string $phone): string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        return strlen($digits) < 8 ? '****' : substr($digits, 0, 4).'****'.substr($digits, -4);
    }
}
