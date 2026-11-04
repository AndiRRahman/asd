<?php

namespace App\Http\Controllers\Panel;

use App\Domain\BusinessRuleException;
use App\Domain\Catalog\StockService;
use App\Http\Controllers\Controller;
use App\Models\Origin;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Support\HashId;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Riwayat mutasi dan stok opname per titik asal (bagian 4.3).
 */
class StockController extends Controller
{
    public function index(Request $request): Response
    {
        $movements = StockMovement::query()
            ->with(['variant.product', 'user:id,name'])
            ->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))
            ->when($request->query('q'), fn ($q, $s) => $q->whereHas('variant', fn ($v) => $v->where('sku', 'like', "%{$s}%")))
            ->latest('id')->paginate(30)->withQueryString();

        return Inertia::render('panel/stock/index', [
            'movements' => [
                'data' => $movements->getCollection()->map(fn (StockMovement $m) => [
                    'id' => $m->id, 'sku' => $m->variant->sku, 'product' => $m->variant->product->name, 'variant' => $m->variant->label(),
                    'type' => $m->type, 'qty_change' => $m->qty_change, 'stock_before' => $m->stock_before, 'stock_after' => $m->stock_after,
                    'reason' => $m->reason, 'reference' => $m->reference, 'user' => $m->user?->name ?? 'Sistem', 'at' => $m->created_at->toIso8601String(),
                ]),
                'current_page' => $movements->currentPage(), 'last_page' => $movements->lastPage(),
            ],
            'opnames' => StockOpname::query()->with('origin')->latest('id')->limit(10)->get()->map(fn ($o) => [
                'id' => $o->hashid, 'origin' => $o->origin->name, 'status' => $o->status, 'created_at' => $o->created_at->toIso8601String(), 'posted_at' => $o->posted_at?->toIso8601String(),
            ]),
            'origins' => Origin::query()->where('is_active', true)->get(['id', 'name']),
            'filters' => $request->only(['type', 'q']),
        ]);
    }

    /** Sistem mencatat stok sistem setiap varian saat sesi dimulai. */
    public function startOpname(Request $request): RedirectResponse
    {
        $data = $request->validate(['origin_id' => ['required', 'exists:origins,id']]);

        if (StockOpname::query()->where('origin_id', $data['origin_id'])->where('status', 'terbuka')->exists()) {
            throw new BusinessRuleException('Masih ada sesi opname terbuka untuk titik ini.');
        }

        $opname = DB::transaction(function () use ($data, $request) {
            $opname = StockOpname::query()->create(['origin_id' => $data['origin_id'], 'started_by' => $request->user()->id]);
            $variants = ProductVariant::query()->whereHas('product', fn ($q) => $q->where('origin_id', $data['origin_id'])->where('status', '!=', 'arsip'))->get();

            foreach ($variants as $variant) {
                $opname->items()->create(['variant_id' => $variant->id, 'system_qty_at_start' => $variant->stock]);
            }

            return $opname;
        });

        return redirect("/panel/stok/opname/{$opname->id}");
    }

    public function showOpname(StockOpname $opname): Response
    {
        $opname->load(['origin', 'items.variant.product']);

        return Inertia::render('panel/stock/opname', [
            'opname' => ['id' => $opname->hashid, 'origin' => $opname->origin->name, 'status' => $opname->status, 'posted_at' => $opname->posted_at?->toIso8601String()],
            'items' => $opname->items->map(fn ($i) => [
                'id' => $i->hashid, 'sku' => $i->variant->sku, 'product' => $i->variant->product->name, 'variant' => $i->variant->label(),
                'system_qty_at_start' => $i->system_qty_at_start, 'counted_qty' => $i->counted_qty, 'difference' => $i->difference,
            ]),
        ]);
    }

    public function countOpname(Request $request, StockOpname $opname): RedirectResponse
    {
        abort_unless($opname->status === 'terbuka', 409);
        $data = $request->validate(['counts' => ['required', 'array'], 'counts.*' => ['nullable', 'integer', 'min:0']]);

        $counts = HashId::decodeKeys($data['counts']);

        foreach ($opname->items as $item) {
            if (array_key_exists($item->id, $counts) && $counts[$item->id] !== null) {
                $item->update(['counted_qty' => $counts[$item->id], 'difference' => $counts[$item->id] - $item->system_qty_at_start]);
            }
        }

        return back();
    }

    /**
     * Setiap selisih menjadi mutasi "koreksi stok opname". Penjualan selama sesi tetap tercatat terpisah:
     * stok 20 saat mulai, hitung fisik 18, terjual 2 selama sesi → setelah posting stok 16 (K24).
     */
    public function postOpname(Request $request, StockOpname $opname, StockService $stock): RedirectResponse
    {
        abort_unless($opname->status === 'terbuka', 409);

        DB::transaction(function () use ($opname, $stock, $request) {
            foreach ($opname->items()->whereNotNull('difference')->where('difference', '!=', 0)->with('variant.product')->get() as $item) {
                $stock->adjust($item->variant, 'opname', $item->difference, $request->user(), reference: 'OPNAME-'.$opname->id, confirmed: true);
            }

            $opname->update(['status' => 'diposting', 'posted_at' => now()]);
        });

        return back();
    }
}
