import { Link } from '@inertiajs/react';
import { OrderStatusPill, PanelPage, Pager, SearchForm, STATUS_LABEL, Table } from '@/components/panel/ui';
import { formatDate, rupiah } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { OrderSummary, Paginated } from '@/types/boka';

type Props = {
    orders: Paginated<OrderSummary & { buyer: string }>;
    counts: Record<string, number>;
    filters: { status: string | null; q: string | null };
};

export default function Orders({ orders, counts, filters }: Props) {
    return (
        <PanelPage title="Pesanan" actions={<SearchForm value={filters.q} placeholder="Kode atau nama pembeli" extra={{ status: filters.status }} />}>
            <nav className="flex gap-2 overflow-x-auto">
                <Link href="/panel/pesanan" className={cn('rounded-full border px-4 py-2 text-sm font-semibold whitespace-nowrap', !filters.status && 'border-primary bg-primary text-primary-foreground')}>Semua</Link>
                {Object.entries(STATUS_LABEL).map(([key, label]) => (
                    <Link key={key} href={`/panel/pesanan?status=${key}`} className={cn('rounded-full border px-4 py-2 text-sm font-semibold whitespace-nowrap', filters.status === key && 'border-primary bg-primary text-primary-foreground')}>
                        {label} <span className="opacity-70">{counts[key] ?? 0}</span>
                    </Link>
                ))}
            </nav>
            <Table head={['Kode', 'Pembeli', 'Item', 'Total', 'Status', 'Dibuat', '']}>
                {orders.data.map((o) => (
                    <tr key={o.code} className="hover:bg-muted/40">
                        <td className="px-4 py-3 font-mono font-semibold">{o.code}</td>
                        <td className="px-4 py-3">{o.buyer}</td>
                        <td className="max-w-56 truncate px-4 py-3">{o.first_item}{o.item_count > 1 && ` +${o.item_count - 1}`}</td>
                        <td className="price px-4 py-3 font-semibold">{rupiah(o.grand_total)}</td>
                        <td className="px-4 py-3"><OrderStatusPill status={o.status} /></td>
                        <td className="px-4 py-3 whitespace-nowrap">{formatDate(o.created_at, 'id', true)}</td>
                        <td className="px-4 py-3 text-right"><Link href={`/panel/pesanan/${o.code}`} className="font-semibold text-primary">Buka →</Link></td>
                    </tr>
                ))}
            </Table>
            <Pager current={orders.current_page} last={orders.last_page} />
        </PanelPage>
    );
}
