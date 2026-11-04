import { Link, usePage } from '@inertiajs/react';
import { ChevronRight, Package } from 'lucide-react';
import { EmptyState } from '@/components/shop/bits';
import { Seo } from '@/components/shop/seo';
import { formatDate, rupiah } from '@/lib/format';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { OrderSummary, Paginated } from '@/types/boka';
import { StatusBadge } from '@/components/shop/status-badge';

const TABS = ['', 'menunggu_pembayaran', 'menunggu_verifikasi', 'diproses', 'dikirim', 'selesai', 'dibatalkan'];

export default function Orders({ orders, status }: { orders: Paginated<OrderSummary>; status: string | null }) {
    const t = useT();
    const { locale } = usePage().props;

    return (
        <div className="mx-auto max-w-4xl px-4 py-6">
            <Seo title={t('orders.title')} />
            <h1 className="text-2xl font-extrabold tracking-tight sm:text-3xl">{t('orders.title')}</h1>
            <nav className="mt-4 flex gap-2 overflow-x-auto pb-1" aria-label={t('orders.filter')}>
                {TABS.map((tab) => (
                    <Link key={tab || 'all'} href={tab ? `/pesanan?status=${tab}` : '/pesanan'} className={cn('rounded-full border px-4 py-2 text-sm font-semibold whitespace-nowrap', (status ?? '') === tab ? 'border-primary bg-primary text-primary-foreground' : 'bg-card')}>
                        {tab ? t(`status.${tab}`) : t('orders.all')}
                    </Link>
                ))}
            </nav>
            <div className="mt-6 space-y-3">
                {orders.data.length === 0 && <EmptyState icon={Package} title={t('orders.empty')} />}
                {orders.data.map((o) => (
                    <Link key={o.code} href={`/pesanan/${o.code}`} className="flex items-center gap-4 rounded-2xl border bg-card p-4 hover:border-primary">
                        <div className="min-w-0 flex-1 space-y-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="font-mono text-sm font-bold">{o.code}</span>
                                <StatusBadge status={o.status} />
                            </div>
                            <p className="truncate text-sm">{o.first_item}{o.item_count > 1 && ` ${t('orders.more_items', { count: o.item_count - 1 })}`}</p>
                            <p className="text-xs text-muted-foreground">{formatDate(o.created_at, locale, true)}</p>
                        </div>
                        <span className="price font-bold">{rupiah(o.grand_total)}</span>
                        <ChevronRight className="size-5 text-muted-foreground" />
                    </Link>
                ))}
            </div>
        </div>
    );
}
