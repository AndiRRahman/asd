<?php

namespace App\Http\Controllers\Panel;

use App\Domain\Order\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Support\HashId;
use App\Support\Reports;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman pertama Admin adalah antrean kerja, bukan grafik (bagian 4).
 */
class DashboardController extends Controller
{
    public function __invoke(Reports $reports): Response
    {
        $today = now('Asia/Makassar')->startOfDay()->utc();
        $month = now('Asia/Makassar')->startOfMonth()->utc();

        return Inertia::render('panel/dashboard', [
            'awaitingVerification' => Order::query()
                ->where('status', OrderStatus::MenungguVerifikasi)
                ->with(['user:id,name', 'latestProof'])
                ->get()
                ->sortBy(fn ($o) => $o->latestProof?->created_at)
                ->values()
                ->map(fn (Order $o) => [
                    'code' => $o->code, 'buyer' => $o->user->name, 'grand_total' => $o->grand_total,
                    'waiting_since' => $o->latestProof?->created_at?->toIso8601String(),
                    'second_approval' => $o->latestProof?->status === 'disetujui_1',
                ]),
            'readyToShip' => Order::query()->where('status', OrderStatus::Diproses)->with('shipments')->oldest('updated_at')->limit(15)->get()
                ->map(fn (Order $o) => ['code' => $o->code, 'packages' => $o->shipments->count(), 'pending' => $o->shipments->reject->isDispatched()->count(), 'since' => $o->updated_at->toIso8601String()]),
            'lowStock' => ProductVariant::query()->whereColumn('stock', '<=', 'low_stock_threshold')
                ->whereHas('product', fn ($q) => $q->active())->with('product')->orderBy('stock')->limit(15)->get()
                ->map(fn ($v) => ['id' => $v->hashid, 'product' => $v->product->name, 'product_id' => HashId::encode($v->product_id), 'variant' => $v->label(), 'sku' => $v->sku, 'stock' => $v->stock, 'threshold' => $v->low_stock_threshold]),
            'revenue' => [
                'today' => $reports->revenue($today, now()),
                'month' => $reports->revenue($month, now()),
                'orders_today' => Order::query()->where('created_at', '>=', $today)->count(),
            ],
        ]);
    }
}
