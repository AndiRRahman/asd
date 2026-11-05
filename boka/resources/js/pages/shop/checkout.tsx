import { router } from '@inertiajs/react';
import { AlertTriangle, Landmark, MapPin, Package, Plus, ShieldCheck, Store, Truck } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { AddressForm } from '@/components/shop/address-form';
import { ProductImage } from '@/components/shop/bits';
import { Seo } from '@/components/shop/seo';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
    isGuest?: boolean;
};

type Destination = { id: string; label: string; district: string };

const METHOD_ICON = { ekspedisi: Truck, lokal: MapPin, ambil: Store };

export default function Checkout({ addresses, banks, quote: initialQuote, defaultAddressId, idempotencyKey, isGuest = false }: Props) {
    const t = useT();
    const [addressId, setAddressId] = useState<string | null>(defaultAddressId);
    const [guestData, setGuestData] = useState({
        name: '',
        phone: '',
        email: '',
        destination_id: '',
        district: '',
        destination_label: '',
        address: '',
        postal_code: '',
    });
    const [destQuery, setDestQuery] = useState('');
    const [destOptions, setDestOptions] = useState<Destination[]>([]);

    const [shipping, setShipping] = useState<Record<string, string>>({});
    const [bankId, setBankId] = useState<string | null>(banks[0]?.id ?? null);
    const [quote, setQuote] = useState<Quote>(initialQuote);
    const [loading, setLoading] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [addOpen, setAddOpen] = useState(false);
    const controller = useRef<AbortController | null>(null);

    // Pencarian kecamatan untuk tamu
    useEffect(() => {
        if (!isGuest) return;
        const abortCtrl = new AbortController();
        const timer = window.setTimeout(() => {
            jsonRequest<Destination[]>(`/wilayah?q=${encodeURIComponent(destQuery)}`, { signal: abortCtrl.signal })
                .then(setDestOptions)
                .catch(() => undefined);
        }, 200);

        return () => {
            window.clearTimeout(timer);
            abortCtrl.abort();
        };
    }, [destQuery, isGuest]);

    // Rincian biaya dihitung ulang di server setiap pilihan berubah (bagian 6).
    useEffect(() => {
        controller.current?.abort();
        controller.current = new AbortController();
        setLoading(true);

        const payload: Record<string, any> = {
            shipping,
        };

        if (isGuest) {
            payload.destination_id = guestData.destination_id || null;
            payload.district = guestData.district || null;
            payload.destination_label = guestData.destination_label || null;
            payload.guest_name = guestData.name || null;
            payload.guest_phone = guestData.phone || null;
            payload.guest_address = guestData.address || null;
        } else {
            payload.address_id = addressId;
        }

        jsonRequest<Quote>('/checkout/rincian', {
            method: 'POST',
            signal: controller.current.signal,
            body: payload,
        })
            .then(setQuote)
            .catch((error) => {
                if (error instanceof HttpError && error.status === 409) {
                    router.visit('/keranjang');
                }
            })
            .finally(() => setLoading(false));
    }, [addressId, isGuest, guestData.destination_id, shipping]);

    const allChosen = quote.packages.length > 0 && quote.packages.every((p) => p.selected);
    const hasAddress = isGuest
        ? Boolean(guestData.name && guestData.phone && guestData.destination_id && guestData.address)
        : Boolean(addressId);
    const canSubmit = hasAddress && bankId && allChosen && !loading && !submitting;

    function placeOrder() {
        if (!canSubmit) {
            return;
        }
        setSubmitting(true);

        const payload: Record<string, any> = {
            shipping,
            bank_account_id: bankId,
            idempotency_key: idempotencyKey,
        };

        if (isGuest) {
            payload.guest_name = guestData.name;
            payload.guest_phone = guestData.phone;
            payload.guest_email = guestData.email || null;
            payload.destination_id = guestData.destination_id;
            payload.district = guestData.district;
            payload.destination_label = guestData.destination_label;
            payload.guest_address = guestData.address;
            payload.postal_code = guestData.postal_code || null;
        } else {
            payload.address_id = addressId;
        }

        router.post('/checkout', payload, { onFinish: () => setSubmitting(false) });
    }

    return (
        <>
            <Seo title={t('checkout.title')} />
            <div className="mx-auto max-w-7xl px-4 py-6 pb-40 lg:pb-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <h1 className="text-2xl font-extrabold tracking-tight sm:text-3xl">{t('checkout.title')}</h1>
                    {isGuest && (
                        <span className="rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">
                            Pesan Langsung Tanpa Login
                        </span>
                    )}
                </div>

                <div className="mt-6 grid gap-8 lg:grid-cols-[1fr_380px]">
                    <div className="space-y-6">
                        <section className="rounded-2xl border bg-card p-5">
                            <div className="mb-4 flex items-center justify-between gap-2">
                                <h2 className="flex items-center gap-2 font-extrabold">
                                    <MapPin className="size-5 text-primary" />
                                    1. {isGuest ? 'Data Pembeli & Alamat Pengiriman' : t('checkout.address')}
                                </h2>
                                {!isGuest && (
                                    <Button variant="ghost" size="sm" onClick={() => setAddOpen(true)}>
                                        <Plus /> {t('checkout.new_address')}
                                    </Button>
                                )}
                            </div>

                            {isGuest ? (
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="guest_name">Nama Lengkap Penerima <span className="text-destructive">*</span></Label>
                                        <Input
                                            id="guest_name"
                                            placeholder="Contoh: Budi Santoso"
                                            value={guestData.name}
                                            onChange={(e) => setGuestData((d) => ({ ...d, name: e.target.value }))}
                                            required
                                        />
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="guest_phone">No. WhatsApp / Telepon <span className="text-destructive">*</span></Label>
                                        <Input
                                            id="guest_phone"
                                            inputMode="tel"
                                            placeholder="08xxxxxxxxxx"
                                            value={guestData.phone}
                                            onChange={(e) => setGuestData((d) => ({ ...d, phone: e.target.value }))}
                                            required
                                        />
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="guest_email">Email (Opsional untuk bukti/invoice)</Label>
                                        <Input
                                            id="guest_email"
                                            type="email"
                                            placeholder="email@contoh.com"
                                            value={guestData.email}
                                            onChange={(e) => setGuestData((d) => ({ ...d, email: e.target.value }))}
                                        />
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="guest_dest">Kecamatan di Kabupaten Bone <span className="text-destructive">*</span></Label>
                                        <Input
                                            id="guest_dest"
                                            list="guest-dest-options"
                                            placeholder="Ketik nama kecamatan..."
                                            value={destQuery || guestData.destination_label}
                                            onChange={(e) => {
                                                const val = e.target.value;
                                                setDestQuery(val);
                                                const match = destOptions.find((o) => o.label.toLowerCase() === val.toLowerCase() || o.district.toLowerCase() === val.toLowerCase());
                                                if (match) {
                                                    setGuestData((d) => ({
                                                        ...d,
                                                        destination_id: match.id,
                                                        district: match.district,
                                                        destination_label: match.label,
                                                    }));
                                                    setShipping({});
                                                }
                                            }}
                                        />
                                        <datalist id="guest-dest-options">
                                            {destOptions.map((o) => (
                                                <option key={o.id} value={o.label} />
                                            ))}
                                        </datalist>
                                    </div>
                                    <div className="grid gap-1.5 sm:col-span-2">
                                        <Label htmlFor="guest_address">Alamat Lengkap <span className="text-destructive">*</span></Label>
                                        <textarea
                                            id="guest_address"
                                            rows={3}
                                            className="rounded-lg border border-input bg-card px-3 py-2 text-sm"
                                            placeholder="Nama jalan, RT/RW, Dusun/Lingkungan, Desa/Kelurahan..."
                                            value={guestData.address}
                                            onChange={(e) => setGuestData((d) => ({ ...d, address: e.target.value }))}
                                            required
                                        />
                                    </div>
                                    <div className="grid gap-1.5 sm:col-span-2">
                                        <Label htmlFor="guest_postal">Kode Pos (Opsional)</Label>
                                        <Input
                                            id="guest_postal"
                                            inputMode="numeric"
                                            placeholder="927xx"
                                            value={guestData.postal_code}
                                            onChange={(e) => setGuestData((d) => ({ ...d, postal_code: e.target.value }))}
                                        />
                                    </div>
                                </div>
                            ) : addresses.length === 0 ? (
                                <p className="text-sm text-muted-foreground">{t('checkout.no_address')}</p>
                            ) : (
                                <div className="grid gap-3 sm:grid-cols-2">
                                    {addresses.map((a) => (
                                        <label
                                            key={a.id}
                                            className={cn(
                                                'flex cursor-pointer gap-3 rounded-xl border p-4 text-sm',
                                                addressId === a.id && 'border-primary bg-primary-soft/50',
                                            )}
                                        >
                                            <input
                                                type="radio"
                                                name="address"
                                                className="mt-1 size-4 accent-[var(--primary)]"
                                                checked={addressId === a.id}
                                                onChange={() => {
                                                    setAddressId(a.id);
                                                    setShipping({});
                                                }}
                                            />
                                            <span>
                                                <span className="font-bold">{a.label}</span> · {a.recipient}
                                                <br />
                                                <span className="text-muted-foreground">
                                                    {a.full_address}, {a.destination_label}
                                                </span>
                                            </span>
                                        </label>
                                    ))}
                                </div>
                            )}
                        </section>

                        <section className="space-y-4">
                            <h2 className="flex items-center gap-2 font-extrabold">
                                <Package className="size-5 text-primary" /> 2. {t('checkout.packages')}
                            </h2>
                            {quote.packages.length > 1 && (
                                <p className="rounded-xl border border-gold/50 bg-gold-soft p-3 text-sm">
                                    {t('checkout.multi_package_notice', { count: quote.packages.length })}
                                </p>
                            )}
                            {quote.expedition_unavailable && hasAddress && (
                                <p className="flex items-center gap-2 rounded-xl border p-3 text-sm text-muted-foreground">
                                    <AlertTriangle className="size-4" /> {t('checkout.expedition_unavailable')}
                                </p>
                            )}
                            {quote.packages.map((pkg) => (
                                <div key={pkg.origin.id} className="rounded-2xl border bg-card">
                                    <div className="flex flex-wrap items-center justify-between gap-2 border-b px-5 py-3 text-sm">
                                        <span className="font-bold">
                                            {t('checkout.package_from', { number: pkg.number, origin: pkg.origin.name })}
                                        </span>
                                        <span className="text-muted-foreground">{grams(pkg.weight_gram)}</span>
                                    </div>
                                    <ul className="divide-y px-5">
                                        {pkg.items.map((item) => (
                                            <li key={item.variant_id} className="flex items-center gap-3 py-3 text-sm">
                                                <div className="w-12 shrink-0 overflow-hidden rounded-md border">
                                                    <ProductImage image={item.product.image} alt="" />
                                                </div>
                                                <span className="min-w-0 flex-1">
                                                    <span className="line-clamp-1 font-medium">{item.product.name}</span>
                                                    <span className="text-xs text-muted-foreground">
                                                        {item.qty} × {rupiah(item.unit_price)}
                                                        {item.variant_label && ` · ${item.variant_label}`}
                                                    </span>
                                                </span>
                                                <span className="price font-semibold">{rupiah(item.line_total)}</span>
                                            </li>
                                        ))}
                                    </ul>
                                    <fieldset className="space-y-2 border-t bg-background/60 px-5 py-4">
                                        <legend className="sr-only">{t('checkout.shipping_method')}</legend>
                                        {!hasAddress && (
                                            <p className="text-sm text-muted-foreground">{t('checkout.choose_address_first')}</p>
                                        )}
                                        {hasAddress && pkg.options.length === 0 && (
                                            <p className="text-sm text-destructive">{t('checkout.no_shipping')}</p>
                                        )}
                                        {pkg.options.map((opt) => {
                                            const Icon = METHOD_ICON[opt.method];

                                            return (
                                                <label
                                                    key={opt.key}
                                                    className={cn(
                                                        'flex min-h-11 cursor-pointer items-center gap-3 rounded-lg border bg-card px-3 py-2 text-sm',
                                                        pkg.selected === opt.key && 'border-primary',
                                                    )}
                                                >
                                                    <input
                                                        type="radio"
                                                        name={`ship-${pkg.origin.id}`}
                                                        className="size-4 accent-[var(--primary)]"
                                                        checked={pkg.selected === opt.key}
                                                        onChange={() => setShipping((s) => ({ ...s, [pkg.origin.id]: opt.key }))}
                                                    />
                                                    <Icon className="size-4 text-primary" aria-hidden="true" />
                                                    <span className="flex-1">
                                                        <span className="font-semibold">{opt.label}</span>
                                                        {opt.etd && (
                                                            <span className="block text-xs text-muted-foreground">
                                                                {opt.method === 'ambil' ? `${pkg.origin.address} · ${opt.etd}` : opt.etd}
                                                            </span>
                                                        )}
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
                            <h2 className="mb-2 flex items-center gap-2 font-extrabold">
                                <Landmark className="size-5 text-primary" /> 3. Pembayaran Langsung / COD
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Pemesanan diproses langsung oleh tim Karang Taruna. Pembayaran dapat dilakukan tunai saat barang diterima (COD) atau kesepakatan via WhatsApp.
                            </p>
                        </section>
                    </div>

                    <aside className="fixed inset-x-0 bottom-14 z-30 border-t bg-background p-4 md:bottom-0 lg:static lg:sticky lg:top-24 lg:self-start lg:border-0 lg:bg-transparent lg:p-0">
                        <div className="space-y-3 lg:rounded-2xl lg:border lg:bg-card lg:p-5">
                            <h2 className="hidden font-extrabold lg:block">{t('checkout.summary')}</h2>
                            <dl className={cn('hidden space-y-2 text-sm lg:block', loading && 'opacity-60')}>
                                <div className="flex justify-between">
                                    <dt>{t('checkout.subtotal')}</dt>
                                    <dd className="price">{rupiah(quote.subtotal)}</dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt>{t('checkout.shipping_total', { count: quote.packages.length })}</dt>
                                    <dd className="price">{rupiah(quote.shipping_cost)}</dd>
                                </div>
                                {quote.service_fee > 0 && (
                                    <div className="flex justify-between">
                                        <dt>{t('checkout.service_fee')}</dt>
                                        <dd className="price">{rupiah(quote.service_fee)}</dd>
                                    </div>
                                )}
                                <div className="flex justify-between text-muted-foreground">
                                    <dt>{t('checkout.unique_code')}</dt>
                                    <dd>{t('checkout.unique_code_note')}</dd>
                                </div>
                            </dl>
                            <div className="flex items-center justify-between gap-4 lg:border-t lg:pt-3">
                                <span className="text-sm font-semibold">{t('checkout.total')}</span>
                                <span className={cn('price text-xl font-extrabold', loading && 'opacity-60')}>
                                    {rupiah(quote.grand_total)}
                                </span>
                            </div>
                            <Button size="lg" className="w-full" disabled={!canSubmit} onClick={placeOrder}>
                                {submitting ? t('checkout.placing') : t('checkout.place_order')}
                            </Button>
                            {!allChosen && hasAddress && (
                                <p className="text-center text-xs text-muted-foreground">{t('checkout.choose_all_shipping')}</p>
                            )}
                        </div>
                    </aside>
                </div>
            </div>

            <Dialog open={addOpen} onOpenChange={setAddOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{t('checkout.new_address')}</DialogTitle>
                    </DialogHeader>
                    <AddressForm
                        onDone={() => {
                            setAddOpen(false);
                            router.reload({
                                only: ['addresses', 'defaultAddressId'],
                                onSuccess: (page) => setAddressId((page.props as unknown as Props).defaultAddressId),
                            });
                        }}
                    />
                </DialogContent>
            </Dialog>
        </>
    );
}
