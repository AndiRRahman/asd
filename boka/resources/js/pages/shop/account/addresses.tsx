import { router } from '@inertiajs/react';
import { MapPin, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { AddressForm } from '@/components/shop/address-form';
import { EmptyState } from '@/components/shop/bits';
import { Seo } from '@/components/shop/seo';
import { Button } from '@/components/ui/button';
import { useT } from '@/lib/i18n';
import type { Address } from '@/types/boka';

export default function Addresses({ addresses }: { addresses: Address[] }) {
    const t = useT();
    const [editing, setEditing] = useState<Address | 'new' | null>(addresses.length === 0 ? 'new' : null);

    return (
        <div className="mx-auto max-w-4xl space-y-6 px-4 py-6">
            <Seo title={t('address.title')} />
            <div className="flex items-center justify-between gap-3">
                <h1 className="text-2xl font-extrabold">{t('address.title')}</h1>
                {editing === null && <Button onClick={() => setEditing('new')}><Plus /> {t('checkout.new_address')}</Button>}
            </div>

            {editing && (
                <section className="rounded-2xl border bg-card p-5">
                    <AddressForm address={editing === 'new' ? null : editing} onDone={() => setEditing(null)} />
                </section>
            )}

            {addresses.length === 0 && !editing && <EmptyState icon={MapPin} title={t('address.empty')} />}

            <ul className="grid gap-3 sm:grid-cols-2">
                {addresses.map((a) => (
                    <li key={a.id} className="rounded-2xl border bg-card p-5 text-sm">
                        <p className="font-bold">{a.label} {a.is_default && <span className="ml-1 rounded bg-primary-soft px-1.5 py-0.5 text-[11px] text-primary">{t('address.default')}</span>}</p>
                        <p className="mt-1">{a.recipient} · {a.phone}</p>
                        <p className="text-muted-foreground">{a.full_address}, {a.destination_label} {a.postal_code}</p>
                        <div className="mt-3 flex gap-2">
                            <Button variant="outline" size="sm" onClick={() => setEditing(a)}><Pencil /> {t('common.edit')}</Button>
                            <Button variant="ghost" size="sm" onClick={() => router.delete(`/akun/alamat/${a.id}`, { preserveScroll: true })}><Trash2 /> {t('common.delete')}</Button>
                        </div>
                    </li>
                ))}
            </ul>
        </div>
    );
}
