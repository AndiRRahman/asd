import { useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { jsonRequest } from '@/lib/http';
import { useT } from '@/lib/i18n';
import type { Address } from '@/types/boka';

type Destination = { id: string; label: string; district: string };

/**
 * Kecamatan dipilih dari daftar tujuan penyedia ongkir, bukan diketik bebas (3.2).
 */
export function AddressForm({ address, onDone }: { address?: Address | null; onDone?: () => void }) {
    const t = useT();
    const form = useForm({
        label: address?.label ?? 'Rumah',
        recipient: address?.recipient ?? '',
        phone: address?.phone ?? '',
        destination_id: address?.destination_id ?? '',
        district: address?.district ?? '',
        destination_label: address?.destination_label ?? '',
        full_address: address?.full_address ?? '',
        postal_code: address?.postal_code ?? '',
        is_default: address?.is_default ?? false,
    });
    const [query, setQuery] = useState('');
    const [options, setOptions] = useState<Destination[]>([]);

    useEffect(() => {
        const controller = new AbortController();
        const id = window.setTimeout(() => {
            jsonRequest<Destination[]>(`/wilayah?q=${encodeURIComponent(query)}`, { signal: controller.signal }).then(setOptions).catch(() => undefined);
        }, 200);

        return () => {
            window.clearTimeout(id);
            controller.abort();
        };
    }, [query]);

    function submit(e: React.FormEvent) {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => onDone?.() };

        if (address) {
            form.put(`/akun/alamat/${address.id}`, options);
        } else {
            form.post('/akun/alamat', options);
        }
    }

    return (
        <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
            <div className="grid gap-1.5">
                <Label htmlFor="label">{t('address.label')}</Label>
                <Input id="label" value={form.data.label} onChange={(e) => form.setData('label', e.target.value)} />
                <InputError message={form.errors.label} />
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor="recipient">{t('address.recipient')}</Label>
                <Input id="recipient" value={form.data.recipient} onChange={(e) => form.setData('recipient', e.target.value)} autoComplete="name" />
                <InputError message={form.errors.recipient} />
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor="phone">{t('address.phone')}</Label>
                <Input id="phone" inputMode="tel" placeholder="08xxxxxxxxxx" value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} autoComplete="tel" />
                <InputError message={form.errors.phone} />
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor="destination">{t('address.district')}</Label>
                <Input id="destination" list="destination-options" placeholder={t('address.district_placeholder')} value={query || form.data.destination_label} onChange={(e) => {
                    setQuery(e.target.value);
                    const match = options.find((o) => o.label === e.target.value);
                    form.setData((data) => ({ ...data, destination_id: match?.id ?? '', district: match?.district ?? '', destination_label: match?.label ?? '' }));
                }} />
                <datalist id="destination-options">
                    {options.map((o) => <option key={o.id} value={o.label} />)}
                </datalist>
                <InputError message={form.errors.destination_id && t('address.district_required')} />
            </div>
            <div className="grid gap-1.5 sm:col-span-2">
                <Label htmlFor="full_address">{t('address.full_address')}</Label>
                <textarea id="full_address" rows={3} className="rounded-lg border border-input bg-card px-3 py-2" value={form.data.full_address} onChange={(e) => form.setData('full_address', e.target.value)} placeholder={t('address.full_address_placeholder')} />
                <InputError message={form.errors.full_address} />
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor="postal_code">{t('address.postal_code')}</Label>
                <Input id="postal_code" inputMode="numeric" value={form.data.postal_code} onChange={(e) => form.setData('postal_code', e.target.value)} />
            </div>
            <label className="flex min-h-11 items-center gap-3 self-end text-sm font-semibold">
                <input type="checkbox" className="size-5 accent-[var(--primary)]" checked={form.data.is_default} onChange={(e) => form.setData('is_default', e.target.checked)} />
                {t('address.make_default')}
            </label>
            <div className="sm:col-span-2">
                <Button type="submit" disabled={form.processing}>{t('address.save')}</Button>
            </div>
        </form>
    );
}
