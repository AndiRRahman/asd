<?php

namespace App\Support;

use App\Domain\Order\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\SearchLog;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Query laporan (bagian 4.6). Dasbor, tabel laporan, ekspor, dan tool MCP memakai query yang sama,
 * sehingga angkanya selalu cocok. Waktu ditampilkan dalam WITA; database menyimpan UTC.
 */
class Reports
{
    public const PAID = [OrderStatus::Diproses, OrderStatus::Dikirim, OrderStatus::Selesai];

    public function revenue(DateTimeInterface $from, DateTimeInterface $to): int
    {
        return (int) Order::query()->whereIn('status', self::PAID)->whereBetween('created_at', [$from, $to])->sum('grand_total');
    }

    /** Omzet dan jumlah pesanan per hari (WITA). */
    public function salesByDay(DateTimeInterface $from, DateTimeInterface $to): Collection
    {
        return Order::query()->whereIn('status', self::PAID)->whereBetween('created_at', [$from, $to])
            ->get(['grand_total', 'created_at'])
            ->groupBy(fn ($o) => $o->created_at->timezone('Asia/Makassar')->toDateString())
            ->map(fn ($g, $day) => ['date' => $day, 'orders' => $g->count(), 'revenue' => (int) $g->sum('grand_total'), 'average' => (int) round($g->avg('grand_total'))])
            ->sortKeys()->values();
    }

    /** Penjualan per produsen: bahan pembagian hasil dan laporan pertanggungjawaban. */
    public function salesByProducer(DateTimeInterface $from, DateTimeInterface $to): Collection
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->join('producers', 'producers.id', '=', 'products.producer_id')
            ->whereIn('orders.status', array_map(fn ($s) => $s->value, self::PAID))
            ->whereBetween('orders.created_at', [$from, $to])
            ->groupBy('producers.id', 'producers.name', 'producers.type')
            ->selectRaw('producers.id, producers.name, producers.type, sum(order_items.qty) as items, sum(order_items.qty * order_items.unit_price) as revenue, count(distinct orders.id) as orders')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($r) => ['producer' => $r->name, 'type' => $r->type, 'items' => (int) $r->items, 'orders' => (int) $r->orders, 'revenue' => (int) $r->revenue]);
    }

    public function topProducts(DateTimeInterface $from, DateTimeInterface $to, int $limit = 10): Collection
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.status', array_map(fn ($s) => $s->value, self::PAID))
            ->whereBetween('orders.created_at', [$from, $to])
            ->groupBy('order_items.product_name')
            ->selectRaw('order_items.product_name as product, sum(order_items.qty) as qty, sum(order_items.qty * order_items.unit_price) as revenue')
            ->orderByDesc('qty')->limit($limit)->get()
            ->map(fn ($r) => ['product' => $r->product, 'qty' => (int) $r->qty, 'revenue' => (int) $r->revenue]);
    }

    /** Kata kunci yang dicari warga tetapi belum dijual: masukan langsung bagi produsen. */
    public function searchKeywords(DateTimeInterface $from, DateTimeInterface $to, bool $withoutResults): Collection
    {
        return SearchLog::query()->whereBetween('created_at', [$from, $to])
            ->when($withoutResults, fn ($q) => $q->where('results_count', 0), fn ($q) => $q->where('results_count', '>', 0))
            ->groupBy('query')->selectRaw('query, count(*) as total, max(created_at) as last_searched')
            ->orderByDesc('total')->limit(50)->get()
            ->map(fn ($r) => ['query' => $r->query, 'total' => (int) $r->total, 'last_searched' => $r->last_searched]);
    }

    public function lowStock(): Collection
    {
        return ProductVariant::query()->whereColumn('stock', '<=', 'low_stock_threshold')
            ->with('product.origin')->orderBy('stock')->get()
            ->map(fn ($v) => ['sku' => $v->sku, 'product' => $v->product->name, 'variant' => $v->label(), 'origin' => $v->product->origin?->name, 'stock' => $v->stock, 'threshold' => $v->low_stock_threshold]);
    }

    public function voucherUsage(DateTimeInterface $from, DateTimeInterface $to): Collection
    {
        return DB::table('voucher_usages')
            ->join('vouchers', 'vouchers.id', '=', 'voucher_usages.voucher_id')
            ->whereBetween('voucher_usages.created_at', [$from, $to])
            ->groupBy('vouchers.code', 'voucher_usages.status')
            ->selectRaw('vouchers.code, voucher_usages.status, count(*) as total')
            ->get();
    }
}
