<?php

namespace App\Http\Controllers\Panel;

use App\Domain\BusinessRuleException;
use App\Domain\Catalog\ProductPublisher;
use App\Domain\Catalog\ProductStatus;
use App\Domain\Catalog\SimilarityChecker;
use App\Domain\Catalog\StockService;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Origin;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductRedirect;
use App\Models\ProductSimilarityDecision;
use App\Models\ProductVariant;
use App\Models\Tag;
use App\Support\HashId;
use App\Support\ImageConverter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengelolaan barang (bagian 4.1 sampai 4.3).
 */
class ProductController extends Controller
{
    public function __construct(private ProductPublisher $publisher, private SimilarityChecker $similarity, private StockService $stock) {}

    public function index(Request $request): Response
    {
        $products = Product::query()
            ->with(['cover', 'producer', 'category', 'variants'])
            ->when($request->query('q'), fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest('id')->paginate(20)->withQueryString();

        return Inertia::render('panel/products/index', [
            'products' => [
                'data' => $products->getCollection()->map(fn (Product $p) => [
                    'id' => $p->hashid, 'name' => $p->name, 'slug' => $p->slug, 'status' => $p->status->value,
                    'image' => $p->cover?->url(), 'producer' => $p->producer?->name, 'category' => $p->category?->name,
                    'stock' => $p->variants->sum('stock'), 'variants' => $p->variants->count(),
                    'price_min' => $p->variants->min('price'), 'low' => $p->variants->contains(fn ($v) => $v->stock <= $v->low_stock_threshold),
                ]),
                'current_page' => $products->currentPage(), 'last_page' => $products->lastPage(), 'total' => $products->total(),
            ],
            'filters' => $request->only(['q', 'status']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('panel/products/form', [...$this->formOptions(), 'product' => null]);
    }

    public function edit(Product $product): Response
    {
        $product->load(['variants', 'images', 'tags']);

        return Inertia::render('panel/products/form', [
            ...$this->formOptions(),
            'product' => [
                'id' => $product->hashid, 'slug' => $product->slug, 'status' => $product->status->value,
                'name' => ['id' => $product->getTranslation('name', 'id', false), 'en' => $product->getTranslation('name', 'en', false)],
                'description' => ['id' => $product->getTranslation('description', 'id', false), 'en' => $product->getTranslation('description', 'en', false)],
                'brewing_instructions' => ['id' => $product->getTranslation('brewing_instructions', 'id', false), 'en' => $product->getTranslation('brewing_instructions', 'en', false)],
                'category_id' => $product->category_id, 'producer_id' => $product->producer_id, 'origin_id' => $product->origin_id,
                'is_featured' => $product->is_featured, 'tags' => $product->tags->pluck('name')->implode(', '),
                'similarity_resolved' => $product->similarity_resolved,
                'images' => $product->images->map(fn (ProductImage $i) => ['id' => $i->hashid, 'url' => $i->url(), 'is_cover' => $i->is_cover, 'width' => $i->width]),
                'variants' => $product->variants->map(fn (ProductVariant $v) => [
                    'id' => $v->hashid, 'sku' => $v->sku, 'barcode' => $v->barcode, 'options' => $v->options ?? [],
                    'price' => $v->price, 'compare_price' => $v->compare_price, 'stock' => $v->stock, 'weight_gram' => $v->weight_gram,
                    'low_stock_threshold' => $v->low_stock_threshold, 'max_per_order' => $v->max_per_order,
                    'movements' => $v->movements()->with('user:id,name')->limit(8)->get()->map(fn ($m) => [
                        'type' => $m->type, 'qty_change' => $m->qty_change, 'stock_after' => $m->stock_after, 'reason' => $m->reason,
                        'reference' => $m->reference, 'user' => $m->user?->name, 'at' => $m->created_at->toIso8601String(),
                    ]),
                ]),
            ],
            'unmet' => $this->publisher->unmetRequirements($product),
        ]);
    }

    /** Pemeriksaan produk serupa sebelum simpan (4.2). */
    public function checkSimilarity(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'], 'category_id' => ['nullable', 'integer'], 'producer_id' => ['nullable', 'integer'],
            'exclude_id' => ['nullable', 'string'], 'skus' => ['array'], 'barcodes' => ['array'], 'weight_gram' => ['nullable', 'integer'],
        ]);

        $candidates = collect([null, ...($data['skus'] ?? []), ...($data['barcodes'] ?? [])])->flatMap(fn ($code) => $this->similarity->candidates([
            'name' => $data['name'], 'category_id' => $data['category_id'] ?? null, 'producer_id' => $data['producer_id'] ?? null,
            'exclude_id' => HashId::decode($data['exclude_id'] ?? null), 'weight_gram' => $data['weight_gram'] ?? null,
            'sku' => in_array($code, $data['skus'] ?? [], true) ? $code : null,
            'barcode' => in_array($code, $data['barcodes'] ?? [], true) ? $code : null,
        ]))->sortByDesc('score')->unique(fn ($c) => $c['product']->id)->values();

        return response()->json([
            'blocks' => SimilarityChecker::blocks($candidates),
            'candidates' => $candidates->map(fn ($c) => [
                'id' => $c['product']->hashid, 'name' => $c['product']->name, 'status' => $c['product']->status->value,
                'score' => $c['score'], 'level' => $c['level'],
                'variants' => $c['product']->variants->map(fn ($v) => ['id' => $v->hashid, 'label' => $v->label() ?? 'Standar', 'sku' => $v->sku, 'stock' => $v->stock]),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        // Keputusan Admin atas kandidat serupa (tambah stok / varian baru / terpisah).
        if (($decision = $data['similarity_decision'] ?? null) && $decision['action'] !== 'terpisah') {
            return $this->applyToExisting($request, $data, $decision);
        }

        $product = DB::transaction(function () use ($request, $data) {
            $product = Product::query()->create([
                ...$this->productAttributes($data),
                'slug' => Str::slug($data['name']['id']).'-'.Str::lower(Str::random(4)),
                'status' => ProductStatus::Aktif,
                'similarity_resolved' => true,
            ]);
            $this->recordDecision($request, $product, $data['similarity_decision'] ?? null);
            $this->syncVariants($request, $product, $data['variants']);
            $this->syncImagesAndTags($request, $product, $data);

            return $product;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Produk berhasil disimpan dan langsung aktif di toko!']);

        return redirect("/panel/produk");
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validated($request, $product);

        DB::transaction(function () use ($request, $product, $data) {
            $oldName = $product->getTranslation('name', 'id');
            $product->update($this->productAttributes($data));

            if ($oldName !== $data['name']['id']) {
                $this->recordDecision($request, $product, $data['similarity_decision'] ?? null);
            }

            $this->syncVariants($request, $product, $data['variants']);
            $this->syncImagesAndTags($request, $product, $data);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Perubahan produk tersimpan.']);

        return back();
    }

    public function status(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::enum(ProductStatus::class)]]);
        $this->publisher->changeStatus($product, ProductStatus::from($data['status']));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Status produk diubah.']);

        return back();
    }

    /** Mutasi stok dengan alasan; stok tidak pernah ditimpa langsung (4.3). */
    public function adjustStock(Request $request, ProductVariant $variant): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:masuk,keluar,retur'],
            'qty' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:120'],
            'reference' => ['nullable', 'string', 'max:60'],
            'note' => ['nullable', 'string', 'max:200'],
            'password' => ['nullable', 'string'],
        ]);

        $confirmed = filled($data['password'] ?? null) && Hash::check($data['password'], $request->user()->password);

        if (filled($data['password'] ?? null) && ! $confirmed) {
            throw ValidationException::withMessages(['password' => 'Password salah.']);
        }

        $change = $data['type'] === 'keluar' ? -$data['qty'] : $data['qty'];
        $this->stock->adjust($variant, $data['type'], $change, $request->user(), $data['reason'] ?? null, $data['reference'] ?? null, $data['note'] ?? null, $confirmed);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Stok {$variant->sku} sekarang {$variant->stock}."]);

        return back();
    }

    /** Gabungkan produk yang terlanjur dobel (4.2). */
    public function merge(Request $request, Product $product): RedirectResponse
    {
        // Kode produk utama (HashID) disalin Admin dari daftar produk.
        $data = $request->validate(['target_id' => ['required', 'string', Rule::notIn([$product->hashid])]]);
        $target = Product::query()->findOrFail(HashId::decodeOrFail(trim($data['target_id'])));

        if ($target->origin_id !== $product->origin_id) {
            throw new BusinessRuleException('Penggabungan ditolak: titik asal kedua produk berbeda.');
        }

        DB::transaction(function () use ($request, $product, $target) {
            foreach ($product->variants as $variant) {
                $twin = $target->variants()->where('options_key', $variant->options_key)->first();

                if ($twin) {
                    if ($variant->stock > 0) {
                        $this->stock->adjust($twin, 'masuk', $variant->stock, $request->user(), 'gabung produk', $product->slug, confirmed: true);
                        $this->stock->adjust($variant, 'keluar', -$variant->stock, $request->user(), 'gabung produk', $target->slug);
                    }
                } else {
                    $variant->update(['product_id' => $target->id]);
                }
            }

            DB::table('reviews')->where('product_id', $product->id)->update(['product_id' => $target->id]);
            DB::table('wishlists')->where('product_id', $product->id)->whereNotIn('user_id', DB::table('wishlists')->where('product_id', $target->id)->pluck('user_id'))->update(['product_id' => $target->id]);
            DB::table('product_daily_stats')->where('product_id', $product->id)->delete();
            $target->increment('sold_count', $product->sold_count);

            ProductRedirect::query()->where('product_id', $product->id)->update(['product_id' => $target->id]);
            ProductRedirect::query()->create(['old_slug' => $product->slug, 'product_id' => $target->id]);
            $product->update(['status' => ProductStatus::Arsip, 'slug' => $product->slug.'-digabung-'.$product->id]);

            activity()->performedOn($target)->causedBy($request->user())->withProperties(['merged_product_id' => $product->id])->log('Produk digabung');
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Produk digabung. Tautan lama dialihkan ke produk utama.']);

        return redirect("/panel/produk/{$target->id}/edit");
    }

    private function applyToExisting(Request $request, array $data, array $decision): RedirectResponse
    {
        $target = Product::query()->findOrFail($decision['product_id']);

        if ($target->producer_id !== (int) $data['producer_id'] || $target->origin_id !== (int) $data['origin_id']) {
            throw new BusinessRuleException('Tambah stok atau varian baru hanya untuk produsen dan titik asal yang sama. Pilih "Simpan sebagai produk terpisah".');
        }

        $variantInput = $data['variants'][0];

        DB::transaction(function () use ($request, $decision, $target, $variantInput) {
            if ($decision['action'] === 'tambah_stok') {
                $variant = ProductVariant::query()->where('product_id', $target->id)->findOrFail($decision['variant_id']);
                $this->stock->adjust($variant, 'masuk', max(1, (int) ($variantInput['stock'] ?? 0)), $request->user(), note: 'Dari pemeriksaan produk serupa');
            } else {
                $this->syncVariants($request, $target, [...$target->variants->map(fn ($v) => ['id' => $v->id, ...$v->only(['sku', 'barcode', 'options', 'price', 'compare_price', 'weight_gram', 'low_stock_threshold', 'max_per_order'])])->all(), $variantInput]);
            }

            ProductSimilarityDecision::query()->create(['product_id' => $target->id, 'candidate_product_id' => $target->id, 'score' => $decision['score'] ?? 1, 'decision' => $decision['action'], 'user_id' => $request->user()->id]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => $decision['action'] === 'tambah_stok' ? 'Stok ditambahkan ke produk lama.' : 'Varian baru ditambahkan ke produk lama.']);

        return redirect("/panel/produk/{$target->id}/edit");
    }

    private function recordDecision(Request $request, Product $product, ?array $decision): void
    {
        if (! $decision || $decision['action'] !== 'terpisah') {
            return;
        }

        foreach ($decision['candidate_ids'] ?? [] as $candidateId) {
            ProductSimilarityDecision::query()->updateOrCreate(
                ['product_id' => $product->id, 'candidate_product_id' => $candidateId],
                ['score' => $decision['score'] ?? 0, 'decision' => 'terpisah', 'reason' => $decision['reason'] ?? null, 'user_id' => $request->user()->id],
            );
        }
    }

    private function syncVariants(Request $request, Product $product, array $variants): void
    {
        $keep = [];

        foreach ($variants as $input) {
            $options = collect($input['options'] ?? [])->filter(fn ($v, $k) => filled($k) && filled($v))->all();
            $attributes = [
                'barcode' => filled($input['barcode'] ?? null) ? $input['barcode'] : null,
                'options' => $options,
                'options_key' => ProductPublisher::optionsKey($options),
                'price' => (int) $input['price'],
                'compare_price' => filled($input['compare_price'] ?? null) ? (int) $input['compare_price'] : null,
                'weight_gram' => (int) $input['weight_gram'],
                'low_stock_threshold' => filled($input['low_stock_threshold'] ?? null) ? (int) $input['low_stock_threshold'] : config('boka.low_stock_default'),
                'max_per_order' => filled($input['max_per_order'] ?? null) ? (int) $input['max_per_order'] : null,
            ];

            $variant = isset($input['id']) ? $product->variants()->find($input['id']) : null;

            if ($variant) {
                $variant->update([...$attributes, 'sku' => ($input['sku'] ?? null) ?: $variant->sku]);
            } else {
                $variant = $product->variants()->create([...$attributes, 'sku' => ($input['sku'] ?? null) ?: ProductPublisher::generateSku($product->load('category'), $options), 'stock' => 0]);

                if (($input['stock'] ?? 0) > 0) {
                    $this->stock->adjust($variant, 'masuk', (int) $input['stock'], $request->user(), note: 'Stok awal', confirmed: true);
                }
            }

            $keep[] = $variant->id;
        }

        // Varian yang pernah dipesan tidak dihapus agar riwayat tetap utuh.
        $product->variants()->whereNotIn('id', $keep)->whereDoesntHave('movements', fn ($q) => $q->where('type', 'checkout'))->each(fn ($v) => $v->stock === 0 && $v->delete());
    }

    private function syncImagesAndTags(Request $request, Product $product, array $data): void
    {
        $files = $request->file('images', []);
        if ($files instanceof \Illuminate\Http\UploadedFile) {
            $files = [$files];
        }

        foreach ($files as $i => $file) {
            if (! ($file instanceof \Illuminate\Http\UploadedFile) || ! $file->isValid()) {
                continue;
            }
            $converted = ImageConverter::convertAndStore($file, 'produk');
            $hasCover = $product->images()->where('is_cover', true)->exists();
            $product->images()->create([
                'path' => $converted['path'],
                'width' => $converted['width'],
                'height' => $converted['height'],
                'is_cover' => ! $hasCover && $i === 0,
                'sort_order' => $product->images()->count(),
            ]);
        }

        if ($cover = $data['cover_image_id'] ?? null) {
            $product->images()->update(['is_cover' => false]);
            $product->images()->whereKey($cover)->update(['is_cover' => true]);
        }

        if ($remove = $data['remove_image_ids'] ?? []) {
            $product->images()->whereIn('id', $remove)->delete();
        }

        $tags = collect(explode(',', $data['tags'] ?? ''))->map(fn ($t) => Str::lower(trim($t)))->filter()->unique();
        $product->tags()->sync($tags->map(fn ($t) => Tag::query()->firstOrCreate(['name' => $t])->id));
    }

    private function productAttributes(array $data): array
    {
        $originId = $data['origin_id'] ?? null;
        if (! $originId && ! empty($data['producer_id'])) {
            $producer = Producer::find($data['producer_id']);
            $originId = $producer?->default_origin_id ?? Origin::query()->value('id');
        }

        return [
            'name' => array_filter($data['name']),
            'name_normalized' => SimilarityChecker::normalize($data['name']['id']),
            'description' => array_filter($data['description'] ?? []),
            'brewing_instructions' => array_filter($data['brewing_instructions'] ?? []),
            'category_id' => $data['category_id'] ?? null,
            'producer_id' => $data['producer_id'],
            'origin_id' => $originId,
            'is_featured' => (bool) ($data['is_featured'] ?? false),
        ];
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $data = $request->validate([
            'name.id' => ['required', 'string', 'max:200'], 'name.en' => ['nullable', 'string', 'max:200'],
            'description.id' => ['nullable', 'string', 'max:5000'], 'description.en' => ['nullable', 'string', 'max:5000'],
            'brewing_instructions.id' => ['nullable', 'string', 'max:5000'], 'brewing_instructions.en' => ['nullable', 'string', 'max:5000'],
            'category_id' => ['nullable', 'exists:categories,id'], 'producer_id' => ['required', 'exists:producers,id'],
            'origin_id' => ['nullable', 'exists:origins,id'], 'is_featured' => ['boolean'], 'tags' => ['nullable', 'string', 'max:300'],
            'variants' => ['required', 'array', 'min:1'],
            'variants.*.id' => ['nullable', 'string'],
            'variants.*.sku' => ['nullable', 'string', 'max:40', 'distinct'],
            'variants.*.barcode' => ['nullable', 'string', 'max:40', 'distinct'],
            'variants.*.options' => ['nullable', 'array'],
            'variants.*.price' => ['required', 'integer', 'min:1'],
            'variants.*.compare_price' => ['nullable', 'integer', 'gt:variants.*.price'],
            'variants.*.stock' => ['nullable', 'integer', 'min:0'],
            'variants.*.weight_gram' => ['required', 'integer', 'min:1'],
            'variants.*.low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'variants.*.max_per_order' => ['nullable', 'integer', 'min:1'],
            'images' => ['array'], 'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'cover_image_id' => ['nullable', 'string'], 'remove_image_ids' => ['array'],
            'similarity_decision' => ['nullable', 'array'],
            'similarity_decision.action' => ['required_with:similarity_decision', 'in:tambah_stok,varian_baru,terpisah'],
            'similarity_decision.product_id' => ['nullable', 'string'],
            'similarity_decision.variant_id' => ['nullable', 'string'],
            'similarity_decision.candidate_ids' => ['array'],
            'similarity_decision.score' => ['nullable', 'numeric'],
            'similarity_decision.reason' => ['required_if:similarity_decision.action,terpisah', 'nullable', 'string', 'max:200'],
        ], ['images.*.max' => 'Ukuran foto produk maksimal 5 MB.']);
        $this->checkUniqueCodes($request, $product);

        return $this->decodeIds($data);
    }

    /** ID varian, foto, dan kandidat produk serupa datang dari browser sebagai HashID. */
    private function decodeIds(array $data): array
    {
        foreach ($data['variants'] as $i => $variant) {
            $data['variants'][$i]['id'] = HashId::decode($variant['id'] ?? null);
        }

        $data['cover_image_id'] = HashId::decode($data['cover_image_id'] ?? null);
        $data['remove_image_ids'] = HashId::decodeMany($data['remove_image_ids'] ?? []);

        if (isset($data['similarity_decision'])) {
            $data['similarity_decision']['product_id'] = HashId::decode($data['similarity_decision']['product_id'] ?? null);
            $data['similarity_decision']['variant_id'] = HashId::decode($data['similarity_decision']['variant_id'] ?? null);
            $data['similarity_decision']['candidate_ids'] = HashId::decodeMany($data['similarity_decision']['candidate_ids'] ?? []);
        }

        return $data;
    }

    /** SKU dan barcode wajib unik di seluruh toko (duplikat pasti, K19). */
    private function checkUniqueCodes(Request $request, ?Product $product): void
    {
        foreach ($request->input('variants', []) as $i => $v) {
            foreach (['sku', 'barcode'] as $field) {
                if (filled($v[$field] ?? null) && ProductVariant::query()->where($field, $v[$field])->when(HashId::decode($v['id'] ?? null), fn ($q, $id) => $q->whereKeyNot($id))->exists()) {
                    throw ValidationException::withMessages(["variants.{$i}.{$field}" => strtoupper($field)." {$v[$field]} sudah dipakai produk lain. Pilih produk lama atau ganti kodenya."]);
                }
            }
        }
    }

    private function formOptions(): array
    {
        return [
            'categories' => Category::query()->with('parent')->orderBy('parent_id')->orderBy('sort_order')->get()->map(fn ($c) => ['value' => $c->id, 'label' => ($c->parent ? $c->parent->name.' › ' : '').$c->name]),
            'producers' => Producer::query()->orderBy('name')->get()->map(fn ($p) => ['value' => $p->id, 'label' => $p->name, 'origin_id' => $p->default_origin_id]),
            'origins' => Origin::query()->get()->map(fn ($o) => ['value' => $o->id, 'label' => $o->name.($o->is_active ? '' : ' (nonaktif)')]),
            'stockReasons' => ['rusak', 'hilang', 'kedaluwarsa', 'sampel promosi', 'lainnya'],
        ];
    }
}
