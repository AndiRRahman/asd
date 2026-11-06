import { Link, router, useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, Check, CheckCircle2, Copy, FileText, MapPin, Package, ShieldAlert, Star, Store, Truck, Upload } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { splitDuration, useCountdown } from '@/components/shop/bits';
import { Seo } from '@/components/shop/seo';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useClipboard } from '@/hooks/use-clipboard';
import { formatDate, rupiah } from '@/lib/format';
import { useLocalePath, useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { OrderDetail } from '@/types/boka';
import { StatusBadge } from '@/components/shop/status-badge';

/** Cincin progres yang berkurang dan berubah emas saat sisa 2 jam (12.3). */
function DeadlineRing({ deadline, totalHours }: { deadline: string; totalHours: number }) {
    const t = useT();
    const remaining = useCountdown(deadline);
    const { hours, minutes, seconds } = splitDuration(remaining);
    const fraction = remaining === null ? 1 : Math.max(0, Math.min(1, remaining / (totalHours * 3600 * 1000)));
    const urgent = remaining !== null && remaining < 2 * 3600 * 1000;
    const circumference = 2 * Math.PI * 52;

    return (
        <div className="flex items-center gap-5">
            <svg viewBox="0 0 120 120" className="size-28 shrink-0 -rotate-90" role="img" aria-label={t('order.time_left', { time: `${hours}:${minutes}` })}>
                <circle cx="60" cy="60" r="52" fill="none" strokeWidth="10" className="stroke-muted" />
                <circle cx="60" cy="60" r="52" fill="none" strokeWidth="10" strokeLinecap="round" strokeDasharray={circumference} strokeDashoffset={circumference * (1 - fraction)} className={cn('transition-[stroke-dashoffset] duration-1000', urgent ? 'stroke-gold' : 'stroke-primary')} />
            </svg>
            <div>
                <p className="text-sm text-muted-foreground">{t('order.pay_before')}</p>
                <p className="price text-3xl font-extrabold" role="timer">{hours}:{minutes}:{seconds}</p>
                {urgent && <p className="mt-1 text-sm font-semibold">{t('order.hurry')}</p>}
            </div>
        </div>
    );
}

function CopyRow({ label, value, display }: { label: string; value: string; display?: string }) {
    const t = useT();
    const [, copy] = useClipboard();
    const [done, setDone] = useState(false);

    return (
        <div className="flex items-center justify-between gap-3 rounded-xl border bg-background p-3">
            <div>
                <p className="text-xs text-muted-foreground">{label}</p>
                <p className="price text-lg font-extrabold">{display ?? value}</p>
            </div>
            <Button variant="outline" size="sm" className="h-11" onClick={() => { void copy(value); setDone(true); window.setTimeout(() => setDone(false), 1500); }}>
                {done ? <Check className="text-leaf" /> : <Copy />} {done ? t('common.copied') : t('common.copy')}
            </Button>
        </div>
    );
}

function ProofForm({ order }: { order: OrderDetail }) {
    const t = useT();
    const form = useForm<{ file: File | null; sender_bank: string; sender_name: string; transferred_at: string }>({
        file: null,
        sender_bank: '',
        sender_name: '',
        transferred_at: new Date().toISOString().slice(0, 10),
    });

    return (
        <form onSubmit={(e) => { e.preventDefault(); form.post(`/pesanan/${order.code}/bukti`, { forceFormData: true, preserveScroll: true }); }} className="grid gap-4 sm:grid-cols-2">
            <div className="grid gap-1.5 sm:col-span-2">
                <Label htmlFor="file">{t('order.proof_file')}</Label>
                <input id="file" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)} className="rounded-lg border border-dashed border-input bg-background p-3 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-primary file:px-3 file:py-2 file:text-primary-foreground" />
                <p className="text-xs text-muted-foreground">{t('order.proof_hint')}</p>
                <InputError message={form.errors.file} />
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor="sender_bank">{t('order.sender_bank')}</Label>
                <Input id="sender_bank" value={form.data.sender_bank} onChange={(e) => form.setData('sender_bank', e.target.value)} placeholder="BRI / BNI / Mandiri" />
                <InputError message={form.errors.sender_bank} />
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor="sender_name">{t('order.sender_name')}</Label>
                <Input id="sender_name" value={form.data.sender_name} onChange={(e) => form.setData('sender_name', e.target.value)} />
                <InputError message={form.errors.sender_name} />
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor="transferred_at">{t('order.transferred_at')}</Label>
                <Input id="transferred_at" type="date" value={form.data.transferred_at} onChange={(e) => form.setData('transferred_at', e.target.value)} />
                <InputError message={form.errors.transferred_at} />
            </div>
            <div className="flex items-end">
                <Button type="submit" size="lg" className="w-full" disabled={form.processing || !form.data.file}><Upload /> {t('order.upload_proof')}</Button>
            </div>
        </form>
    );
}

function ReviewForm({ code, itemId, onDone }: { code: string; itemId: string; onDone: () => void }) {
    const t = useT();
    const form = useForm<{ order_item_id: string; rating: number; body: string; photos: File[] }>({ order_item_id: itemId, rating: 5, body: '', photos: [] });

    return (
        <form className="mt-3 space-y-3 rounded-xl bg-background p-4" onSubmit={(e) => { e.preventDefault(); form.post(`/pesanan/${code}/ulasan`, { forceFormData: true, preserveScroll: true, onSuccess: onDone }); }}>
            <div className="flex gap-1" role="radiogroup" aria-label={t('order.rating')}>
                {[1, 2, 3, 4, 5].map((r) => (
                    <button key={r} type="button" role="radio" aria-checked={form.data.rating === r} onClick={() => form.setData('rating', r)} className="flex size-11 items-center justify-center" aria-label={`${r}`}>
                        <Star className={cn('size-7', r <= form.data.rating ? 'fill-gold text-gold' : 'text-border')} />
                    </button>
                ))}
            </div>
            <textarea rows={3} className="w-full rounded-lg border border-input bg-card p-3" placeholder={t('order.review_placeholder')} value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} />
            <input type="file" multiple accept="image/*" onChange={(e) => form.setData('photos', Array.from(e.target.files ?? []).slice(0, 3))} className="text-sm" />
            <Button type="submit" disabled={form.processing}>{t('order.send_review')}</Button>
        </form>
    );
}

export default function OrderShow({ order, pickupQr }: { order: OrderDetail; pickupQr: Record<string, string> }) {
    const t = useT();
    const localePath = useLocalePath();
    const { locale } = usePage().props;
    const [reviewing, setReviewing] = useState<string | null>(null);
    const totalHours = order.histories.length > 1 && order.rejection_count > 0 ? 6 : 24;

    return (
        <div className="mx-auto max-w-4xl space-y-6 px-4 py-6">
            <Seo title={`${t('order.title')} ${order.code}`} />
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <Link href="/pesanan" className="text-sm text-muted-foreground hover:text-primary">← {t('orders.title')}</Link>
                    <h1 className="mt-1 flex flex-wrap items-center gap-3 text-2xl font-extrabold"><span className="font-mono">{order.code}</span> <StatusBadge status={order.status} /></h1>
                    <p className="text-sm text-muted-foreground">{formatDate(order.created_at, locale, true)} WITA</p>
                </div>
                <Button variant="outline" asChild><a href={`/pesanan/${order.code}/invoice`} target="_blank" rel="noopener"><FileText /> {t('order.invoice')}</a></Button>
            </div>

            {order.status === 'menunggu_pembayaran' && order.payment_deadline_at && (
                <section className="space-y-4 rounded-2xl border-2 border-primary/30 bg-card p-5">
                    {order.proof?.status === 'ditolak' && (
                        <p role="alert" className="flex items-start gap-2 rounded-xl bg-destructive/10 p-3 text-sm font-semibold text-destructive">
                            <AlertTriangle className="mt-0.5 size-4 shrink-0" /> {t('order.proof_rejected', { reason: order.proof.reject_reason ?? '-' })}
                        </p>
                    )}
                    <DeadlineRing deadline={order.payment_deadline_at} totalHours={totalHours} />
                    <div className="grid gap-3 sm:grid-cols-2">
                        <CopyRow label={t('order.transfer_exact')} value={String(order.grand_total)} display={rupiah(order.grand_total)} />
                        <CopyRow label={`${order.bank.bank_name} · a.n. ${order.bank.account_holder}`} value={order.bank.account_number.replace(/\D/g, '')} display={order.bank.account_number} />
                    </div>
                    <p className="text-sm text-muted-foreground">{t('order.unique_code_explain', { code: order.unique_code })}</p>
                    {/* Peringatan tetap: toko tidak pernah meminta transfer ke rekening lain (11.1 T5). */}
                    <p className="flex items-start gap-2 rounded-xl border border-gold/50 bg-gold-soft p-3 text-sm">
                        <ShieldAlert className="mt-0.5 size-4 shrink-0" /> <span>{t('order.official_warning')} <Link href={localePath('/rekening-resmi')} className="font-semibold underline">{t('footer.official_accounts')}</Link></span>
                    </p>
                    {order.can_upload_proof && <ProofForm order={order} />}
                    {order.can_cancel && (
                        <button type="button" className="text-sm text-muted-foreground underline hover:text-destructive" onClick={() => router.post(`/pesanan/${order.code}/batal`, {}, { preserveScroll: true })}>
                            {t('order.cancel')}
                        </button>
                    )}
                </section>
            )}

            {order.status === 'menunggu_verifikasi' && (
                <section className="space-y-4 rounded-2xl border-2 border-primary/30 bg-card p-5">
                    <div className="rounded-xl border border-gold/40 bg-gold-soft p-4 text-sm">
                        <p className="flex items-center gap-2 text-base font-bold text-foreground">
                            <CheckCircle2 className="size-5 text-primary" /> Pesanan Berhasil Masuk · Menunggu Verifikasi
                        </p>
                        <p className="mt-1 text-muted-foreground">
                            Pesanan Anda telah diterima oleh tim kami dan sedang dalam antrean verifikasi untuk segera diproses.
                        </p>
                    </div>

                    {order.bank && (
                        <div className="space-y-2">
                            <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Informasi Pembayaran</p>
                            <div className="grid gap-3 sm:grid-cols-2">
                                <CopyRow label={t('order.transfer_exact')} value={String(order.grand_total)} display={rupiah(order.grand_total)} />
                                <CopyRow label={`${order.bank.bank_name} · a.n. ${order.bank.account_holder}`} value={order.bank.account_number.replace(/\D/g, '')} display={order.bank.account_number} />
                            </div>
                        </div>
                    )}

                    {order.can_upload_proof && (
                        <details className="text-xs">
                            <summary className="cursor-pointer py-1 font-semibold text-primary hover:underline">
                                {order.proof ? 'Lihat / Unggah Ulang Bukti Transfer' : 'Sudah Transfer? Unggah Bukti Transfer di Sini (Opsional)'}
                            </summary>
                            <div className="mt-3">
                                <ProofForm order={order} />
                            </div>
                        </details>
                    )}

                    {order.can_cancel && (
                        <div className="border-t pt-2">
                            <button type="button" className="text-sm text-muted-foreground underline hover:text-destructive" onClick={() => router.post(`/pesanan/${order.code}/batal`, {}, { preserveScroll: true })}>
                                {t('order.cancel')}
                            </button>
                        </div>
                    )}
                </section>
            )}

            {(order.status === 'kedaluwarsa' || order.status === 'dibatalkan') && (
                <section className="rounded-2xl border bg-muted p-5 text-sm">
                    <p className="font-bold">{t(`order.final_${order.status}`)}</p>
                    {order.cancel_reason && <p className="mt-1">{order.cancel_reason}</p>}
                </section>
            )}

            <section className="space-y-4">
                <h2 className="font-extrabold">{t('order.packages', { count: order.shipments.length })}</h2>
                {order.shipments.map((s) => (
                    <div key={s.id} className="rounded-2xl border bg-card">
                        <div className="flex flex-wrap items-center justify-between gap-2 border-b px-5 py-3 text-sm">
                            <span className="flex items-center gap-2 font-bold">
                                {s.method === 'ambil' ? <Store className="size-4 text-primary" /> : s.method === 'lokal' ? <MapPin className="size-4 text-primary" /> : <Truck className="size-4 text-primary" />}
                                {t('checkout.package_from', { number: s.number, origin: s.origin.name })}
                            </span>
                            <span className="text-muted-foreground">{s.courier ?? t('order.pickup')} {s.service} · {t(`shipment.${s.status}`)}</span>
                        </div>
                        <ul className="divide-y px-5">
                            {order.items.filter((i) => i.shipment_id === s.id).map((item) => (
                                <li key={item.id} className="py-3 text-sm">
                                    <div className="flex items-center justify-between gap-3">
                                        <span><span className="font-medium">{item.product_name}</span>{item.variant_name && <span className="text-muted-foreground"> · {item.variant_name}</span>}<br /><span className="text-xs text-muted-foreground">{item.qty} × {rupiah(item.unit_price)}</span></span>
                                        {order.can_review && !item.reviewed && reviewing !== item.id && <Button variant="outline" size="sm" onClick={() => setReviewing(item.id)}><Star /> {t('order.review')}</Button>}
                                        {item.reviewed && <span className="text-xs font-semibold text-leaf">{t('order.reviewed')}</span>}
                                    </div>
                                    {reviewing === item.id && <ReviewForm code={order.code} itemId={item.id} onDone={() => setReviewing(null)} />}
                                </li>
                            ))}
                        </ul>
                        {(s.tracking_number || s.pickup_code || s.receiver_name) && (
                            <div className="space-y-3 border-t bg-background/60 px-5 py-4 text-sm">
                                {s.tracking_number && (
                                    <p>{t('order.tracking')}: <span className="font-mono font-bold">{s.tracking_number}</span> · <a className="font-semibold text-primary underline" href={`https://cekresi.com/?noresi=${encodeURIComponent(s.tracking_number)}`} target="_blank" rel="noopener noreferrer">{t('order.track')}</a></p>
                                )}
                                {s.pickup_code && (
                                    <div className="flex flex-wrap items-center gap-4">
                                        <div className="rounded-lg bg-white p-2" dangerouslySetInnerHTML={{ __html: pickupQr[s.id] ?? '' }} />
                                        <div>
                                            <p className="text-muted-foreground">{t('order.pickup_code')}</p>
                                            <p className="font-mono text-3xl font-extrabold tracking-[0.3em]">{s.pickup_code}</p>
                                            <p className="text-xs text-muted-foreground">{s.origin.address} · {s.origin.pickup_hours}</p>
                                        </div>
                                    </div>
                                )}
                                {s.receiver_name && <p>{t('order.received_by', { name: s.receiver_name })}</p>}
                            </div>
                        )}
                    </div>
                ))}
            </section>

            {order.can_complete && (
                <Button size="lg" className="w-full" onClick={() => router.post(`/pesanan/${order.code}/diterima`, {}, { preserveScroll: true })}>
                    <Package /> {t('order.received')}
                </Button>
            )}

            <div className="grid gap-6 md:grid-cols-2">
                <section className="rounded-2xl border bg-card p-5">
                    <h2 className="mb-3 font-extrabold">{t('order.cost_breakdown')}</h2>
                    <dl className="space-y-2 text-sm">
                        <div className="flex justify-between"><dt>{t('checkout.subtotal')}</dt><dd className="price">{rupiah(order.subtotal)}</dd></div>
                        {order.voucher_discount > 0 && <div className="flex justify-between text-leaf"><dt>{t('checkout.voucher_discount')}</dt><dd className="price">−{rupiah(order.voucher_discount)}</dd></div>}
                        <div className="flex justify-between"><dt>{t('checkout.shipping_total', { count: order.shipments.length })}</dt><dd className="price">{rupiah(order.shipping_cost)}</dd></div>
                        {order.shipping_discount > 0 && <div className="flex justify-between text-leaf"><dt>{t('checkout.shipping_discount')}</dt><dd className="price">−{rupiah(order.shipping_discount)}</dd></div>}
                        {order.service_fee > 0 && <div className="flex justify-between"><dt>{t('checkout.service_fee')}</dt><dd className="price">{rupiah(order.service_fee)}</dd></div>}
                        <div className="flex justify-between"><dt>{t('checkout.unique_code')}</dt><dd className="price">{rupiah(order.unique_code)}</dd></div>
                        <div className="flex justify-between border-t pt-2 text-base font-extrabold"><dt>{t('checkout.total')}</dt><dd className="price">{rupiah(order.grand_total)}</dd></div>
                    </dl>
                    <p className="mt-4 text-sm text-muted-foreground"><MapPin className="mr-1 inline size-4" />{order.address.recipient} · {order.address.full_address}, {order.address.destination_label}</p>
                </section>
                <section className="rounded-2xl border bg-card p-5">
                    <h2 className="mb-3 font-extrabold">{t('order.timeline')}</h2>
                    <ol className="relative space-y-4 border-l-2 border-border pl-5">
                        {order.histories.map((h, i) => (
                            <li key={i} className="relative">
                                <span className={cn('absolute top-1 -left-[27px] size-3 rotate-45', i === order.histories.length - 1 ? 'bg-primary' : 'bg-border')} />
                                <p className="text-sm font-bold">{t(`status.${h.to}`)}</p>
                                {h.note && <p className="text-sm text-muted-foreground">{h.note}</p>}
                                <p className="text-xs text-muted-foreground">{formatDate(h.at, locale, true)}</p>
                            </li>
                        ))}
                    </ol>
                </section>
            </div>
        </div>
    );
}
