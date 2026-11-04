import { router } from '@inertiajs/react';
import { useState } from 'react';
import { ConfirmButton, PanelPage, Pill, Table } from '@/components/panel/ui';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type Item = { id: string; sku: string; product: string; variant: string | null; system_qty_at_start: number; counted_qty: number | null; difference: number | null };
type Props = { opname: { id: string; origin: string; status: string; posted_at: string | null }; items: Item[] };

/** Selisih lebih dari 5% ditandai untuk diperiksa ulang (4.3). */
export default function Opname({ opname, items }: Props) {
    const [counts, setCounts] = useState<Record<string, string>>(Object.fromEntries(items.map((i) => [i.id, i.counted_qty?.toString() ?? ''])));
    const open = opname.status === 'terbuka';

    const flagged = (i: Item) => {
        const counted = Number(counts[i.id]);
        if (counts[i.id] === '' || Number.isNaN(counted)) {
            return false;
        }
        const diff = Math.abs(counted - i.system_qty_at_start);

        return i.system_qty_at_start === 0 ? diff > 0 : diff / i.system_qty_at_start > 0.05;
    };

    return (
        <PanelPage
            title={`Opname #${opname.id} · ${opname.origin}`}
            description="Isi hasil hitung fisik per varian (bisa lewat HP di gudang). Penjualan selama sesi berjalan tetap tercatat terpisah dan tidak tertimpa."
            actions={<Pill tone={open ? 'warn' : 'good'}>{opname.status}</Pill>}
        >
            <Table head={['SKU', 'Produk', 'Stok sistem saat mulai', 'Hitung fisik', 'Selisih']}>
                {items.map((i) => {
                    const diff = counts[i.id] === '' ? null : Number(counts[i.id]) - i.system_qty_at_start;

                    return (
                        <tr key={i.id} className={cn(flagged(i) && 'bg-gold-soft')}>
                            <td className="px-4 py-2 font-mono text-xs">{i.sku}</td>
                            <td className="px-4 py-2">{i.product}{i.variant && ` · ${i.variant}`}</td>
                            <td className="price px-4 py-2">{i.system_qty_at_start}</td>
                            <td className="px-4 py-2">
                                <input inputMode="numeric" disabled={!open} className="h-11 w-24 rounded-lg border border-input bg-card px-3" value={counts[i.id]} onChange={(e) => setCounts({ ...counts, [i.id]: e.target.value.replace(/\D/g, '') })} />
                            </td>
                            <td className={cn('price px-4 py-2 font-bold', diff !== null && diff < 0 && 'text-destructive', diff !== null && diff > 0 && 'text-leaf')}>
                                {diff === null ? '-' : `${diff > 0 ? '+' : ''}${diff}`} {flagged(i) && <Pill tone="warn">periksa ulang</Pill>}
                            </td>
                        </tr>
                    );
                })}
            </Table>
            {open && (
                <div className="flex flex-wrap gap-2">
                    <Button variant="outline" onClick={() => router.post(`/panel/stok/opname/${opname.id}/hitung`, { counts: Object.fromEntries(Object.entries(counts).map(([k, v]) => [k, v === '' ? null : Number(v)])) }, { preserveScroll: true })}>Simpan hitungan</Button>
                    <ConfirmButton label="Posting sesi" title="Posting sesi opname?" body="Setiap selisih menjadi mutasi koreksi stok opname. Sesi tidak bisa diubah lagi." onConfirm={() => router.post(`/panel/stok/opname/${opname.id}/posting`)} />
                </div>
            )}
        </PanelPage>
    );
}
