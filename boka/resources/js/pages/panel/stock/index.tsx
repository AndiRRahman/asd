import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Card, PanelPage, Pager, Pill, SearchForm, Table, fieldClass } from '@/components/panel/ui';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types/boka';

type Movement = { id: number; sku: string; product: string; variant: string | null; type: string; qty_change: number; stock_before: number; stock_after: number; reason: string | null; reference: string | null; user: string; at: string };
type Props = {
    movements: Paginated<Movement>;
    opnames: { id: string; origin: string; status: string; created_at: string; posted_at: string | null }[];
    origins: { id: number; name: string }[];
    filters: { type?: string; q?: string };
};

const TYPES = ['masuk', 'keluar', 'opname', 'checkout', 'restore', 'retur'];

export default function Stock({ movements, opnames, origins, filters }: Props) {
    const [origin, setOrigin] = useState(origins[0]?.id ?? '');

    return (
        <PanelPage title="Stok & opname" description="Riwayat mutasi tidak bisa diedit atau dihapus. Ubah stok varian dari halaman produk.">
            <Card title="Stok opname per titik asal">
                <div className="flex flex-wrap items-end gap-2">
                    <select className={`${fieldClass} max-w-xs`} value={origin} onChange={(e) => setOrigin(Number(e.target.value))}>
                        {origins.map((o) => <option key={o.id} value={o.id}>{o.name}</option>)}
                    </select>
                    <Button onClick={() => router.post('/panel/stok/opname', { origin_id: origin })}>Mulai sesi opname</Button>
                </div>
                <ul className="mt-4 divide-y text-sm">
                    {opnames.map((o) => (
                        <li key={o.id} className="flex items-center justify-between gap-2 py-2">
                            <Link href={`/panel/stok/opname/${o.id}`} className="font-semibold hover:text-primary">#{o.id} · {o.origin}</Link>
                            <Pill tone={o.status === 'terbuka' ? 'warn' : 'good'}>{o.status}</Pill>
                            <span className="text-muted-foreground">{formatDate(o.created_at, 'id', true)}</span>
                        </li>
                    ))}
                </ul>
            </Card>

            <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex flex-wrap gap-2">
                    {['', ...TYPES].map((t) => (
                        <button key={t || 'semua'} type="button" onClick={() => router.get('/panel/stok', { type: t || undefined, q: filters.q })} className={cn('rounded-full border px-3 py-1.5 text-sm font-semibold capitalize', (filters.type ?? '') === t && 'border-primary bg-primary text-primary-foreground')}>{t || 'Semua'}</button>
                    ))}
                </div>
                <SearchForm value={filters.q} placeholder="Cari SKU" extra={{ type: filters.type }} />
            </div>
            <Table head={['Waktu', 'SKU', 'Produk', 'Jenis', 'Perubahan', 'Sebelum → sesudah', 'Alasan / referensi', 'Oleh']}>
                {movements.data.map((m) => (
                    <tr key={m.id}>
                        <td className="px-4 py-2 whitespace-nowrap">{formatDate(m.at, 'id', true)}</td>
                        <td className="px-4 py-2 font-mono text-xs">{m.sku}</td>
                        <td className="px-4 py-2">{m.product}{m.variant && ` · ${m.variant}`}</td>
                        <td className="px-4 py-2"><Pill>{m.type}</Pill></td>
                        <td className={cn('price px-4 py-2 font-bold', m.qty_change < 0 ? 'text-destructive' : 'text-leaf')}>{m.qty_change > 0 ? '+' : ''}{m.qty_change}</td>
                        <td className="price px-4 py-2">{m.stock_before} → {m.stock_after}</td>
                        <td className="px-4 py-2">{[m.reason, m.reference].filter(Boolean).join(' · ')}</td>
                        <td className="px-4 py-2">{m.user}</td>
                    </tr>
                ))}
            </Table>
            <Pager current={movements.current_page} last={movements.last_page} />
        </PanelPage>
    );
}
