import { router, useForm } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, ImagePlus, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Card, PanelPage, Pill, fieldClass } from '@/components/panel/ui';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { formatDate, rupiah } from '@/lib/format';
import { jsonRequest } from '@/lib/http';
import { cn } from '@/lib/utils';

type Option = { value: number; label: string; origin_id?: number | null };
type Movement = { type: string; qty_change: number; stock_after: number; reason: string | null; reference: string | null; user: string | null; at: string };
type VariantInput = {
    id?: string; sku: string; barcode: string; options: Record<string, string>; price: number | ''; compare_price: number | '' | null;
    stock: number | ''; weight_gram: number | ''; low_stock_threshold: number | ''; max_per_order: number | '' | null; movements?: Movement[];
};
type ProductData = {
    id: string; slug: string; status: string; name: { id: string; en: string | null }; description: { id: string | null; en: string | null };
    category_id: number; producer_id: number; origin_id: number; is_featured: boolean; tags: string; similarity_resolved: boolean;
    images: { id: string; url: string; is_cover: boolean; width: number }[]; variants: (VariantInput & { id: string })[];
};
type Candidate = { id: string; name: string; status: string; score: number; level: string; variants: { id: string; label: string; sku: string; stock: number }[] };
type Props = { categories: Option[]; producers: Option[]; origins: Option[]; stockReasons: string[]; product: ProductData | null; unmet?: string[] };

const emptyVariant = (): VariantInput => ({ sku: '', barcode: '', options: {}, price: '', compare_price: '', stock: 0, weight_gram: '', low_stock_threshold: 5, max_per_order: '' });
const LEVEL: Record<string, string> = { duplikat_pasti: 'Duplikat pasti', kemungkinan_sama: 'Kemungkinan sama', mirip: 'Mirip' };

function StockDialog({ variant, reasons, onClose }: { variant: VariantInput & { id: string }; reasons: string[]; onClose: () => void }) {
    const form = useForm({ type: 'masuk', qty: 1, reason: '', reference: '', note: '', password: '' });
    const [needsPassword, setNeedsPassword] = useState(false);

    return (
        <Dialog open onOpenChange={onClose}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Mutasi stok {variant.sku}</DialogTitle>
                    <DialogDescription>Stok saat ini {variant.stock}. Stok hanya berubah lewat mutasi tambah atau kurang, tidak pernah ditimpa.</DialogDescription>
                </DialogHeader>
                <form className="space-y-3" onSubmit={(e) => {
                    e.preventDefault();
                    form.post(`/panel/varian/${variant.id}/stok`, {
                        preserveScroll: true,
                        onSuccess: () => onClose(),
                        onError: (errors) => {
                            if ((errors as Record<string, string>).business?.includes('password')) {
                                setNeedsPassword(true);
                            }
                        },
                    });
                }}>
                    <select className={fieldClass} value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>
                        <option value="masuk">Stok masuk (penerimaan dari produsen)</option>
                        <option value="keluar">Penyesuaian keluar</option>
                        <option value="retur">Retur diterima (layak jual)</option>
                    </select>
                    <input className={fieldClass} type="number" min={1} value={form.data.qty} onChange={(e) => form.setData('qty', Number(e.target.value))} aria-label="Jumlah" />
                    {form.data.type === 'keluar' && (
                        <select className={fieldClass} value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)}>
                            <option value="">Pilih alasan</option>
                            {reasons.map((r) => <option key={r}>{r}</option>)}
                        </select>
                    )}
                    {form.data.type === 'retur' && (
                        <>
                            <input className={fieldClass} placeholder="Alasan retur" value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} />
                            <input className={fieldClass} placeholder="Kode pesanan" value={form.data.reference} onChange={(e) => form.setData('reference', e.target.value)} />
                        </>
                    )}
                    <input className={fieldClass} placeholder="Catatan (misalnya nomor nota)" value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} />
                    <InputError message={form.errors.qty ?? form.errors.reason ?? (form.errors as Record<string, string>).business} />
                    <Button type="submit" className="w-full" disabled={form.processing}>Simpan mutasi</Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function ProductForm({ categories, producers, origins, stockReasons, product, unmet = [] }: Props) {
    const form = useForm({
        name: { id: product?.name.id ?? '', en: product?.name.en ?? '' },
        description: { id: product?.description.id ?? '', en: product?.description.en ?? '' },
        brewing_instructions: { id: (product as any)?.brewing_instructions?.id ?? '', en: (product as any)?.brewing_instructions?.en ?? '' },
        category_id: product?.category_id ?? '',
        producer_id: product?.producer_id ?? '',
        origin_id: product?.origin_id ?? '',
        is_featured: product?.is_featured ?? false,
        tags: product?.tags ?? '',
        variants: (product?.variants ?? [emptyVariant()]) as VariantInput[],
        images: [] as File[],
        cover_image_id: null as string | null,
        remove_image_ids: [] as string[],
    });
    const errors = form.errors as Record<string, string>;
    const [candidates, setCandidates] = useState<{ blocks: boolean; list: Candidate[] } | null>(null);
    const [decision, setDecision] = useState<{ action: string; product_id?: string; variant_id?: string; reason?: string }>({ action: '' });
    const [stockVariant, setStockVariant] = useState<(VariantInput & { id: string }) | null>(null);
    const [mergeTarget, setMergeTarget] = useState('');

    const setVariant = (i: number, patch: Partial<VariantInput>) => form.setData('variants', form.data.variants.map((v, idx) => (idx === i ? { ...v, ...patch } : v)));

    function save(similarityDecision: Record<string, unknown> | null) {
        form.transform((data) => ({ ...data, similarity_decision: similarityDecision }) as typeof data);
        const options = {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setCandidates(null);
                form.setData((prev) => ({
                    ...prev,
                    images: [],
                    remove_image_ids: [],
                    cover_image_id: null,
                }));
            },
        };
        if (product) {
            form.post(`/panel/produk/${product.id}`, options);
        } else {
            form.post('/panel/produk', options);
        }
    }

    // Pemeriksaan produk serupa saat simpan produk baru atau mengubah nama (4.2).
    async function submit(e: React.FormEvent) {
        e.preventDefault();
        if (product && product.name.id === form.data.name.id) {
            save(null);

            return;
        }
        const result = await jsonRequest<{ blocks: boolean; candidates: Candidate[] }>('/panel/produk/cek-serupa', {
            method: 'POST',
            body: {
                name: form.data.name.id, category_id: form.data.category_id || null, producer_id: form.data.producer_id || null, exclude_id: product?.id ?? null,
                skus: form.data.variants.map((v) => v.sku).filter(Boolean), barcodes: form.data.variants.map((v) => v.barcode).filter(Boolean),
                weight_gram: Number(form.data.variants[0]?.weight_gram) || null,
            },
        });
        if (result.candidates.length === 0) {
            save(null);

            return;
        }
        setDecision({ action: result.blocks ? '' : 'terpisah', reason: result.blocks ? '' : 'saran mirip diabaikan' });
        setCandidates({ blocks: result.blocks, list: result.candidates });
    }

    function confirmDecision() {
        if (!candidates) {
            return;
        }
        save({
            action: decision.action,
            product_id: decision.product_id,
            variant_id: decision.variant_id,
            reason: decision.reason,
            candidate_ids: candidates.list.map((c) => c.id),
            score: candidates.list[0]?.score ?? 0,
        });
    }

    const exactDuplicate = candidates?.list.some((c) => c.level === 'duplikat_pasti');

    return (
        <PanelPage
            title={product ? `Ubah: ${product.name.id}` : 'Produk baru'}
            description={product ? `Status ${product.status}. Perubahan harga tercatat di log audit; pesanan lama tetap memakai harga saat dipesan.` : 'Produk baru akan langsung otomatis aktif dan tampil di katalog publik toko.'}
            actions={product && (
                <>
                    {product.status !== 'aktif' && <Button onClick={() => router.post(`/panel/produk/${product.id}/status`, { status: 'aktif' }, { preserveScroll: true })}><CheckCircle2 /> Aktifkan</Button>}
                    {product.status === 'aktif' && <Button variant="outline" onClick={() => router.post(`/panel/produk/${product.id}/status`, { status: 'nonaktif' }, { preserveScroll: true })}>Nonaktifkan</Button>}
                    {product.status !== 'arsip' && <Button variant="outline" onClick={() => router.post(`/panel/produk/${product.id}/status`, { status: 'arsip' }, { preserveScroll: true })}>Arsipkan</Button>}
                    {product.status === 'aktif' && <Button variant="ghost" asChild><a href={`/produk/${product.slug}`} target="_blank" rel="noopener">Lihat di toko</a></Button>}
                </>
            )}
        >
            {product && unmet.length > 0 && (
                <div className="rounded-xl border border-gold bg-gold-soft p-4 text-sm">
                    <p className="flex items-center gap-2 font-bold"><AlertTriangle className="size-4" /> Syarat tayang yang belum terpenuhi</p>
                    <ul className="mt-2 list-disc pl-6">{unmet.map((m) => <li key={m}>{m}</li>)}</ul>
                </div>
            )}

            <form onSubmit={submit} className="grid gap-6 lg:grid-cols-[1.5fr_1fr]">
                <div className="space-y-6">
                    <Card title="Informasi produk">
                        <div className="grid gap-4">
                            <label className="grid gap-1.5 text-sm font-semibold">Nama Produk (wajib)<input className={fieldClass} value={form.data.name.id} onChange={(e) => form.setData('name', { id: e.target.value, en: '' })} /><InputError message={errors['name.id']} /></label>
                            <label className="grid gap-1.5 text-sm font-semibold">Deskripsi Produk<textarea rows={5} className="rounded-lg border border-input bg-card p-3 font-normal" value={form.data.description.id ?? ''} onChange={(e) => form.setData('description', { id: e.target.value, en: '' })} /></label>
                            <label className="grid gap-1.5 text-sm font-semibold">Cara Seduh / Petunjuk Penyajian (opsional)<textarea rows={3} className="rounded-lg border border-input bg-card p-3 font-normal" placeholder="Contoh: Seduh dengan air 90°C selama 3 menit..." value={form.data.brewing_instructions.id ?? ''} onChange={(e) => form.setData('brewing_instructions', { id: e.target.value, en: '' })} /></label>
                            <label className="grid gap-1.5 text-sm font-semibold">Tag (pisahkan dengan koma, dipakai rekomendasi)<input className={fieldClass} value={form.data.tags} onChange={(e) => form.setData('tags', e.target.value)} placeholder="kopi, oleh-oleh" /></label>
                        </div>
                    </Card>

                    <Card title="Varian, harga, dan stok" actions={<Button type="button" variant="outline" size="sm" onClick={() => form.setData('variants', [...form.data.variants, emptyVariant()])}><Plus /> Varian</Button>}>
                        <div className="space-y-4">
                            {form.data.variants.map((v, i) => (
                                <div key={v.id ?? `new-${i}`} className="rounded-xl border p-4">
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <label className="grid gap-1 text-xs font-semibold">Nama opsi (mis. Ukuran / Berat)<input className={fieldClass} value={Object.keys(v.options)[0] ?? ''} onChange={(e) => setVariant(i, { options: e.target.value ? { [e.target.value]: Object.values(v.options)[0] ?? '' } : {} })} /></label>
                                        <label className="grid gap-1 text-xs font-semibold">Nilai opsi (mis. 250 g)<input className={fieldClass} value={Object.values(v.options)[0] ?? ''} onChange={(e) => setVariant(i, { options: { [Object.keys(v.options)[0] || 'Varian']: e.target.value } })} /></label>
                                        <label className="grid gap-1 text-xs font-semibold">Harga jual (Rp)<input className={fieldClass} type="number" value={v.price} onChange={(e) => setVariant(i, { price: e.target.value === '' ? '' : Number(e.target.value) })} /><InputError message={errors[`variants.${i}.price`]} /></label>
                                        <label className="grid gap-1 text-xs font-semibold">Berat (gram)<input className={fieldClass} type="number" value={v.weight_gram} onChange={(e) => setVariant(i, { weight_gram: e.target.value === '' ? '' : Number(e.target.value) })} /></label>
                                        <label className="grid gap-1 text-xs font-semibold">Ambang stok menipis<input className={fieldClass} type="number" value={v.low_stock_threshold} onChange={(e) => setVariant(i, { low_stock_threshold: e.target.value === '' ? '' : Number(e.target.value) })} /></label>
                                        <label className="grid gap-1 text-xs font-semibold">Batas beli per pesanan<input className={fieldClass} type="number" value={v.max_per_order ?? ''} onChange={(e) => setVariant(i, { max_per_order: e.target.value === '' ? '' : Number(e.target.value) })} placeholder="tanpa batas" /></label>
                                    </div>
                                    <div className="mt-3 flex flex-wrap items-center justify-between gap-2">
                                        {v.id ? (
                                            <span className="flex items-center gap-2 text-sm">Stok <b className="price text-lg">{v.stock}</b> <Button type="button" size="sm" variant="outline" onClick={() => setStockVariant(v as VariantInput & { id: string })}>Mutasi stok</Button></span>
                                        ) : (
                                            <label className="flex items-center gap-2 text-xs font-semibold">Stok awal <input className={`${fieldClass} w-28`} type="number" value={v.stock} onChange={(e) => setVariant(i, { stock: e.target.value === '' ? '' : Number(e.target.value) })} /></label>
                                        )}
                                        {form.data.variants.length > 1 && <Button type="button" variant="ghost" size="sm" onClick={() => form.setData('variants', form.data.variants.filter((_, idx) => idx !== i))}><Trash2 /> Hapus varian</Button>}
                                    </div>
                                    {v.movements && v.movements.length > 0 && (
                                        <details className="mt-3 text-xs">
                                            <summary className="cursor-pointer font-semibold">Riwayat mutasi</summary>
                                            <ul className="mt-2 divide-y">
                                                {v.movements.map((m, k) => <li key={k} className="flex justify-between gap-2 py-1.5"><span>{m.type} {m.reason && `· ${m.reason}`} {m.reference && `· ${m.reference}`}</span><span className={cn('price font-bold', m.qty_change < 0 ? 'text-destructive' : 'text-leaf')}>{m.qty_change > 0 ? '+' : ''}{m.qty_change} → {m.stock_after}</span><span className="text-muted-foreground">{m.user ?? 'Sistem'} · {formatDate(m.at, 'id', true)}</span></li>)}
                                            </ul>
                                        </details>
                                    )}
                                </div>
                            ))}
                        </div>
                        <InputError message={form.errors.variants} />
                    </Card>
                </div>

                <div className="space-y-6">
                    <Card title="Asal dan kategori">
                        <div className="grid gap-4">
                            <label className="grid gap-1.5 text-sm font-semibold">Kategori<select className={fieldClass} value={form.data.category_id} onChange={(e) => form.setData('category_id', Number(e.target.value))}><option value="">Pilih</option>{categories.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}</select><InputError message={form.errors.category_id} /></label>
                            <label className="grid gap-1.5 text-sm font-semibold">Produsen<select className={fieldClass} value={form.data.producer_id} onChange={(e) => {
                                const producer = producers.find((p) => p.value === Number(e.target.value));
                                form.setData((d) => ({ ...d, producer_id: Number(e.target.value), origin_id: d.origin_id || producer?.origin_id || '' }));
                            }}><option value="">Pilih</option>{producers.map((p) => <option key={p.value} value={p.value}>{p.label}</option>)}</select><InputError message={form.errors.producer_id} /></label>
                            <label className="grid gap-1.5 text-sm font-semibold">Titik asal kirim<select className={fieldClass} value={form.data.origin_id} onChange={(e) => form.setData('origin_id', Number(e.target.value))}><option value="">Pilih</option>{origins.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}</select><InputError message={form.errors.origin_id} /></label>
                            <label className="flex items-center gap-3 text-sm font-semibold"><input type="checkbox" className="size-5 accent-[var(--primary)]" checked={form.data.is_featured} onChange={(e) => form.setData('is_featured', e.target.checked)} /> Unggulan</label>
                        </div>
                    </Card>

                    <Card title="Foto Produk">
                        <p className="mb-3 text-xs text-muted-foreground">Format JPG, PNG, atau WebP (maksimal 5 MB per foto). Foto otomatis dioptimasi ke WebP.</p>
                        
                        {/* Foto yang sudah tersimpan */}
                        <div className="grid grid-cols-3 gap-2">
                            {product?.images.filter((img) => !form.data.remove_image_ids.includes(img.id)).map((img) => (
                                <div key={img.id} className={cn('relative overflow-hidden rounded-lg border-2', (form.data.cover_image_id ?? (img.is_cover ? img.id : null)) === img.id ? 'border-primary' : 'border-transparent')}>
                                    <img
                                        src={img.url}
                                        alt=""
                                        className="aspect-square w-full object-cover"
                                        onError={(e) => {
                                            e.currentTarget.style.display = 'none';
                                            const parent = e.currentTarget.parentElement;
                                            if (parent && !parent.querySelector('.img-fallback')) {
                                                const fallback = document.createElement('div');
                                                fallback.className = 'img-fallback aspect-square w-full flex items-center justify-center bg-muted text-destructive text-xs p-2 text-center';
                                                fallback.innerText = 'Foto tidak ditemukan / rusak';
                                                parent.prepend(fallback);
                                            }
                                        }}
                                    />
                                    <div className="absolute inset-x-0 bottom-0 flex justify-between bg-background/90 p-1 text-[11px]">
                                        <button type="button" className="font-semibold text-primary" onClick={() => form.setData('cover_image_id', img.id)}>Sampul</button>
                                        <button type="button" className="text-destructive font-semibold" onClick={() => form.setData('remove_image_ids', [...form.data.remove_image_ids, img.id])}>Hapus</button>
                                    </div>
                                </div>
                            ))}

                            {/* Pratinjau foto baru yang baru dipilih tapi belum disimpan */}
                            {form.data.images.map((file, idx) => (
                                <div key={idx} className="relative overflow-hidden rounded-lg border-2 border-dashed border-primary/50 bg-muted">
                                    <img src={URL.createObjectURL(file)} alt="" className="aspect-square w-full object-cover opacity-80" />
                                    <div className="absolute inset-x-0 bottom-0 flex justify-between bg-background/90 p-1 text-[11px]">
                                        <span className="text-[10px] text-muted-foreground truncate px-1">Baru</span>
                                        <button
                                            type="button"
                                            className="text-destructive font-semibold"
                                            onClick={() => form.setData('images', form.data.images.filter((_, i) => i !== idx))}
                                        >
                                            Batal
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>

                        <label className="mt-3 flex cursor-pointer items-center justify-center gap-2 rounded-lg border-2 border-dashed border-input p-4 text-sm font-semibold hover:border-primary transition-colors">
                            <ImagePlus className="size-4 text-primary" /> {form.data.images.length ? `+ Tambah foto lain (${form.data.images.length} dipilih)` : 'Pilih Foto dari Perangkat'}
                            <input
                                type="file"
                                multiple
                                accept="image/jpeg,image/png,image/webp"
                                className="sr-only"
                                onChange={(e) => {
                                    const selected = Array.from(e.target.files ?? []);
                                    if (selected.length) {
                                        form.setData('images', [...form.data.images, ...selected]);
                                    }
                                }}
                            />
                        </label>
                        <InputError message={Object.entries(errors).find(([k]) => k.startsWith('images'))?.[1]} />
                    </Card>

                    <Button type="submit" size="lg" className="w-full" disabled={form.processing}>Simpan produk</Button>
                    <InputError message={errors.business} />

                    {product && (
                        <Card title="Gabungkan produk dobel">
                            <p className="mb-3 text-xs text-muted-foreground">Produk ini menjadi Arsip; varian, ulasan, wishlist pindah ke produk utama; tautan lama dialihkan (301). Ditolak jika titik asal berbeda.</p>
                            <div className="flex gap-2">
                                <input className={fieldClass} placeholder="Kode produk utama (lihat daftar produk)" value={mergeTarget} onChange={(e) => setMergeTarget(e.target.value.trim())} />
                                <Button type="button" variant="outline" disabled={!mergeTarget} onClick={() => router.post(`/panel/produk/${product.id}/gabung`, { target_id: mergeTarget })}>Gabung</Button>
                            </div>
                        </Card>
                    )}
                </div>
            </form>

            {stockVariant && <StockDialog variant={stockVariant} reasons={stockReasons} onClose={() => setStockVariant(null)} />}

            <Dialog open={candidates !== null} onOpenChange={(open) => !open && setCandidates(null)}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{candidates?.blocks ? 'Produk ini kemungkinan sudah ada' : 'Ada produk yang mirip'}</DialogTitle>
                        <DialogDescription>{candidates?.blocks ? 'Penyimpanan ditahan sampai Anda memilih salah satu dari tiga pilihan.' : 'Ini hanya saran. Anda boleh langsung melanjutkan.'}</DialogDescription>
                    </DialogHeader>
                    <ul className="space-y-2">
                        {candidates?.list.map((c) => (
                            <li key={c.id} className="rounded-lg border p-3 text-sm">
                                <div className="flex items-center justify-between gap-2"><b>{c.name}</b><span className="flex gap-1"><Pill tone={c.level === 'mirip' ? 'muted' : 'bad'}>{LEVEL[c.level]}</Pill><Pill>skor {c.score.toFixed(2)}</Pill><Pill>{c.status}</Pill></span></div>
                                <p className="mt-1 text-xs text-muted-foreground">{c.variants.map((v) => `${v.label} (${v.sku}, stok ${v.stock})`).join(' · ')}</p>
                            </li>
                        ))}
                    </ul>
                    {candidates?.blocks && (
                        <div className="space-y-3">
                            {(
                                <>
                                    <label className="flex items-start gap-3 rounded-lg border p-3 text-sm"><input type="radio" name="decision" checked={decision.action === 'tambah_stok'} onChange={() => setDecision({ action: 'tambah_stok', product_id: candidates.list[0].id, variant_id: candidates.list[0].variants[0]?.id })} /><span><b>Tambah stok ke produk lama</b><br />Barang benar-benar sama. Stok awal di form menjadi stok masuk pada varian lama.</span></label>
                                    {decision.action === 'tambah_stok' && (
                                        <select className={fieldClass} value={`${decision.product_id}:${decision.variant_id}`} onChange={(e) => { const [p, v] = e.target.value.split(':'); setDecision({ ...decision, product_id: p, variant_id: v }); }}>
                                            {candidates.list.flatMap((c) => c.variants.map((v) => <option key={v.id} value={`${c.id}:${v.id}`}>{c.name} · {v.label} ({v.sku})</option>))}
                                        </select>
                                    )}
                                    <label className="flex items-start gap-3 rounded-lg border p-3 text-sm"><input type="radio" name="decision" checked={decision.action === 'varian_baru'} onChange={() => setDecision({ action: 'varian_baru', product_id: candidates.list[0].id })} /><span><b>Jadikan varian baru</b><br />Barang sama tetapi ukuran, berat, atau rasa berbeda.</span></label>
                                </>
                            )}
                            <label className="flex items-start gap-3 rounded-lg border p-3 text-sm"><input type="radio" name="decision" checked={decision.action === 'terpisah'} onChange={() => setDecision({ action: 'terpisah', reason: '' })} /><span><b>Simpan sebagai produk terpisah</b><br />Produsen, titik asal, atau mutu berbeda. Alasan wajib.</span></label>
                            {decision.action === 'terpisah' && (
                                <select className={fieldClass} value={decision.reason} onChange={(e) => setDecision({ ...decision, reason: e.target.value })}>
                                    <option value="">Pilih alasan</option>
                                    <option>produsen berbeda</option><option>titik asal berbeda</option><option>mutu/grade berbeda</option><option>lainnya</option>
                                </select>
                            )}
                            {exactDuplicate && <p className="text-sm text-destructive">SKU atau barcode sama dengan produk lain. Ganti kodenya di form, atau pilih produk lama.</p>}
                        </div>
                    )}
                    <Button onClick={confirmDecision} disabled={!decision.action || (decision.action === 'terpisah' && !decision.reason) || (exactDuplicate && decision.action === 'terpisah')}>
                        Lanjutkan simpan
                    </Button>
                </DialogContent>
            </Dialog>

            {product && <p className="text-xs text-muted-foreground">Harga terendah saat ini {rupiah(Math.min(...product.variants.map((v) => Number(v.price) || 0)))}.</p>}
        </PanelPage>
    );
}
