import { router } from '@inertiajs/react';
import { AlertTriangle, Landmark, MapPin, Package, Plus, ShieldCheck, Store, Ticket, Truck } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { AddressForm } from '@/components/shop/address-form';
import { ProductImage } from '@/components/shop/bits';
import { Seo } from '@/components/shop/seo';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { grams, rupiah } from '@/lib/format';
import { HttpError, jsonRequest } from '@/lib/http';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { Address, Quote } from '@/types/boka';

type Props = {
    addresses: Address[];
    banks: { id: string; bank_name: string; account_number: string; account_holder: string }[];
    quote: Quote;
    defaultAddressId: string | null;
    idempotencyKey: string;
};

const METHOD_ICON = { ekspedisi: Truck, lokal: MapPin, ambil: Store };

export default function Checkout({ addresses, banks, quote: initialQuote, defaultAddressId, idempotencyKey }: Props) {
    const t = useT();
    const [addressId, setAddressId] = useState<string | null>(defaultAddressId);
    const [shipping, setShipping] = useState<Record<string, string>>({});
    const [voucher, setVoucher] = useState('');
    const [shipVoucher, setShipVoucher] = useState('');
    const [applied, setApplied] = useState({ voucher: '', shipVoucher: '' });
    const [bankId, setBankId] = useState<string | null>(banks[0]?.id ?? null);
    const [quote, setQuote] = useState<Quote>(initialQuote);
    const [loading, setLoading] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [addOpen, setAddOpen] = useState(addresses.length === 0);
    const controller = useRef<AbortController | null>(null);

    // Rincian biaya dihitung ulang di server setiap pilihan berubah (bagian 6).
    useEffect(() => {
        controller.current?.abort();
        controller.current = new AbortController();
        setLoading(true);
        jsonRequest<Quote>('/checkout/rincian', {
            method: 'POST',
            signal: controller.current.signal,
            body: { address_id: addressId, shipping, voucher_code: applied.voucher || null, shipping_voucher_code: applied.shipVoucher || null },
        })
            .then(setQuote)
            .catch((error) => {
                // Titik 4 (4.4): stok berubah, kembali ke keranjang dengan item bermasalah ditandai.
                if (error instanceof HttpError && error.status === 409) {
                    router.visit('/keranjang');
                }
            })
            .finally(() => setLoading(false));
    }, [addressId, shipping, applied]);

    const allChosen = quote.packages.every((p) => p.selected);
    const canSubmit = addressId && bankId && allChosen && !loading && !submitting;

    function placeOrder() {
        if (!canSubmit) {
            return;
        }
        setSubmitting(true);
        router.post('/checkout', {
            address_id: addressId,
            shipping,
            bank_account_id: bankId,
            voucher_code: applied.voucher || null,
            shipping_voucher_code: applied.shipVoucher || null,
            idempotency_key: idempotencyKey,
        }, { onFinish: () => setSubmitting(false) });
    }

    return (
        <>
            <Seo title={t('checkout.title')} />
            <div className="mx-auto max-w-7xl px-4 py-6 pb-40 lg:pb-6">
                <h1 className="text-2xl font-extrabold tracking-tight sm:text-3xl">{t('checkout.title')}</h1>

                <div className="mt-6 grid gap-8 lg:grid-cols-[1fr_380px]">
                    <div className="space-y-6">
                        <section className="rounded-2xl border bg-card p-5">
                            <div className="mb-4 flex items-center justify-between gap-2">
                                <h2 className="flex items-center gap-2 font-extrabold"><MapPin className="size-5 text-primary" /> 1. {t('checkout.address')}</h2>
                                <Button variant="ghost" size="sm" onClick={() => setAddOpen(true)}><Plus /> {t('checkout.new_address')}</Button>
                            </div>
                            {addresses.length === 0 ? (
                                <p className="text-sm text-muted-foreground">{t('checkout.no_address')}</p>
                            ) : (
                                <div className="grid gap-3 sm:grid-cols-2">
                                    {addresses.map((a) => (
                                        <label key={a.id} className={cn('flex cursor-pointer gap-3 rounded-xl border p-4 text-sm', addressId === a.id && 'border-primary bg-primary-soft/50')}>
                                            <input type="radio" name="address" className="mt-1 size-4 accent-[var(--primary)]" checked={addressId === a.id} onChange={() => { setAddressId(a.id); setShipping({}); }} />
                                            <span>
                                                <span className="font-bold">{a.label}</span> · {a.recipient}<br />
                                                <span className="text-muted-foreground">{a.full_address}, {a.destination_label}</span>
                                            </span>
                                        </label>
                                    ))}
                                </div>
                            )}
                        </section>

                        <section className="space-y-4">
                            <h2 className="flex items-center gap-2 font-extrabold"><Package className="size-5 text-primary" /> 2. {t('checkout.packages')}</h2>
                            {quote.packages.length > 1 && (
                                <p className="rounded-xl border border-gold/50 bg-gold-soft p-3 text-sm">{t('checkout.multi_package_notice', { count: quote.packages.length })}</p>
                            )}
                            {quote.expedition_unavailable && addressId && (
                                <p className="flex items-center gap-2 rounded-xl border p-3 text-sm text-muted-foreground"><AlertTriangle className="size-4" /> {t('checkout.expedition_unavailable')}</p>
                            )}
                            {quote.packages.map((pkg) => (
                                <div key={pkg.origin.id} className="rounded-2xl border bg-card">
                                    <div className="flex flex-wrap items-center justify-between gap-2 border-b px-5 py-3 text-sm">
                                        <span className="font-bold">{t('checkout.package_from', { number: pkg.number, origin: pkg.origin.name })}</span>
                                        <span className="text-muted-foreground">{grams(pkg.weight_gram)}</span>
                                    </div>
                                    <ul className="divide-y px-5">
                                        {pkg.items.map((item) => (
                                            <li key={item.variant_id} className="flex items-center gap-3 py-3 text-sm">
                                                <div className="w-12 shrink-0 overflow-hidden rounded-md border"><ProductImage image={item.product.image} alt="" /></div>
                                                <span className="min-w-0 flex-1"><span className="line-clamp-1 font-medium">{item.product.name}</span><span className="text-xs text-muted-foreground">{item.qty} × {rupiah(item.unit_price)}{item.variant_label && ` · ${item.variant_label}`}</span></span>
                                                <span className="price font-semibold">{rupiah(item.line_total)}</span>
                                            </li>
                                        ))}
                                    </ul>
                                    <fieldset className="space-y-2 border-t bg-background/60 px-5 py-4">
                                        <legend className="sr-only">{t('checkout.shipping_method')}</legend>
                                        {!addressId && <p className="text-sm text-muted-foreground">{t('checkout.choose_address_first')}</p>}
                                        {addressId && pkg.options.length === 0 && <p className="text-sm text-destructive">{t('checkout.no_shipping')}</p>}
                                        {pkg.options.map((opt) => {
                                            const Icon = METHOD_ICON[opt.method];

                                            return (
                                                <label key={opt.key} className={cn('flex min-h-11 cursor-pointer items-center gap-3 rounded-lg border bg-card px-3 py-2 text-sm', pkg.selected === opt.key && 'border-primary')}>
                                                    <input type="radio" name={`ship-${pkg.origin.id}`} className="size-4 accent-[var(--primary)]" checked={pkg.selected === opt.key} onChange={() => setShipping((s) => ({ ...s, [pkg.origin.id]: opt.key }))} />
                                                    <Icon className="size-4 text-primary" aria-hidden="true" />
                                                    <span className="flex-1">
                                                        <span className="font-semibold">{opt.label}</span>
                                                        {opt.etd && <span className="block text-xs text-muted-foreground">{opt.method === 'ambil' ? `${pkg.origin.address} · ${opt.etd}` : opt.etd}</span>}
                                                    </span>
                                                    <span className="price font-bold">{opt.cost === 0 ? t('checkout.free') : rupiah(opt.cost)}</span>
                                                </label>
                                            );
                                        })}
                                    </fieldset>
                                </div>
                            ))}
                        </section>

                        <section className="rounded-2xl border bg-card p-5">
                            <h2 className="mb-4 flex items-center gap-2 font-extrabold"><Ticket className="size-5 text-primary" /> 3. {t('checkout.vouchers')}</h2>
                            <div className="grid gap-4 sm:grid-cols-2">
                                {([['voucher', voucher, setVoucher, 'belanja'], ['shipVoucher', shipVoucher, setShipVoucher, 'ongkir']] as const).map(([key, value, set, type]) => (
                                    <form key={key} className="space-y-1.5" onSubmit={(e) => { e.preventDefault(); setApplied((a) => ({ ...a, [key]: value.trim().toUpperCase() })); }}>
                                        <label className="text-sm font-semibold" htmlFor={key}>{t(`checkout.voucher_${type}`)}</label>
                                        <div className="flex gap-2">
                                            <Input id={key} value={value} onChange={(e) => set(e.target.value)} placeholder={t('checkout.voucher_placeholder')} className="uppercase" />
                                            <Button type="submit" variant="outline">{t('checkout.apply')}</Button>
                                        </div>
                                        {quote.voucher_errors[type] && <p className="text-sm text-destructive">{quote.voucher_errors[type]}</p>}
                                        {!quote.voucher_errors[type] && applied[key] && <p className="text-sm text-leaf">{t('checkout.voucher_applied', { code: applied[key] })}</p>}
                                    </form>
                                ))}
                            </div>
                        </section>

                        <section className="rounded-2xl border bg-card p-5">
                            <h2 className="mb-4 flex items-center gap-2 font-extrabold"><Landmark className="size-5 text-primary" /> 4. {t('checkout.bank')}</h2>
                            <div className="grid gap-3 sm:grid-cols-2">
                                {banks.map((b) => (
                                    <label key={b.id} className={cn('flex cursor-pointer gap-3 rounded-xl border p-4 text-sm', bankId === b.id && 'border-primary bg-primary-soft/50')}>
                                        <input type="radio" name="bank" className="mt-1 size-4 accent-[var(--primary)]" checked={bankId === b.id} onChange={() => setBankId(b.id)} />
                                        <span><span className="font-bold">{b.bank_name}</span><br /><span className="text-muted-foreground">a.n. {b.account_holder}</span></span>
                                    </label>
                                ))}
                            </div>
                            <p className="mt-3 flex items-start gap-2 text-xs text-muted-foreground"><ShieldCheck className="size-4 shrink-0 text-leaf" /> {t('checkout.bank_note')}</p>
                        </section>
                    </div>

                    {/* Ringkasan menempel di kanan (desktop) atau bawah (HP) (12.2). */}
                    <aside className="fixed inset-x-0 bottom-14 z-30 border-t bg-background p-4 md:bottom-0 lg:static lg:sticky lg:top-24 lg:self-start lg:border-0 lg:bg-transparent lg:p-0">
                        <div className="space-y-3 lg:rounded-2xl lg:border lg:bg-card lg:p-5">
                            <h2 className="hidden font-extrabold lg:block">{t('checkout.summary')}</h2>
                            <dl className={cn('hidden space-y-2 text-sm lg:block', loading && 'opacity-60')}>
                                <div className="flex justify-between"><dt>{t('checkout.subtotal')}</dt><dd className="price">{rupiah(quote.subtotal)}</dd></div>
                                {quote.voucher_discount > 0 && <div className="flex justify-between text-leaf"><dt>{t('checkout.voucher_discount')}</dt><dd className="price">−{rupiah(quote.voucher_discount)}</dd></div>}
                                <div className="flex justify-between"><dt>{t('checkout.shipping_total', { count: quote.packages.length })}</dt><dd className="price">{rupiah(quote.shipping_cost)}</dd></div>
                                {quote.shipping_discount > 0 && <div className="flex justify-between text-leaf"><dt>{t('checkout.shipping_discount')}</dt><dd className="price">−{rupiah(quote.shipping_discount)}</dd></div>}
                                {quote.service_fee > 0 && <div className="flex justify-between"><dt>{t('checkout.service_fee')}</dt><dd className="price">{rupiah(quote.service_fee)}</dd></div>}
                                <div className="flex justify-between text-muted-foreground"><dt>{t('checkout.unique_code')}</dt><dd>{t('checkout.unique_code_note')}</dd></div>
                            </dl>
                            <div className="flex items-center justify-between gap-4 lg:border-t lg:pt-3">
                                <span className="text-sm font-semibold">{t('checkout.total')}</span>
                                <span className={cn('price text-xl font-extrabold', loading && 'opacity-60')}>{rupiah(quote.grand_total)}</span>
                            </div>
                            {quote.has_flash_sale && <p className="text-xs text-muted-foreground">{t('checkout.flash_deadline')}</p>}
                            <Button size="lg" className="w-full" disabled={!canSubmit} onClick={placeOrder}>
                                {submitting ? t('checkout.placing') : t('checkout.place_order')}
                            </Button>
                            {!allChosen && addressId && <p className="text-center text-xs text-muted-foreground">{t('checkout.choose_all_shipping')}</p>}
                        </div>
                    </aside>
                </div>
            </div>

            <Dialog open={addOpen} onOpenChange={setAddOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader><DialogTitle>{t('checkout.new_address')}</DialogTitle></DialogHeader>
                    <AddressForm onDone={() => { setAddOpen(false); router.reload({ only: ['addresses', 'defaultAddressId'], onSuccess: (page) => setAddressId((page.props as unknown as Props).defaultAddressId) }); }} />
                </DialogContent>
            </Dialog>
        </>
    );
}
