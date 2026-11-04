import { usePage } from '@inertiajs/react';
import { BadgeCheck, CircleAlert } from 'lucide-react';
import { Seo } from '@/components/shop/seo';
import { StatusBadge } from '@/components/shop/status-badge';
import { formatDate, rupiah } from '@/lib/format';
import { useT } from '@/lib/i18n';
import type { OrderStatus } from '@/types/boka';

/** Verifikasi invoice publik tanpa data pribadi (11.1 T14). */
export default function InvoiceVerify({ code, order }: { code: string; order: { code: string; grand_total: number; status: OrderStatus; paid: boolean; created_at: string } | null }) {
    const t = useT();
    const { locale } = usePage().props;

    return (
        <div className="mx-auto max-w-lg px-4 py-12">
            <Seo title={t('invoice.title')} />
            <div className="rounded-2xl border bg-card p-6 text-center">
                {order ? (
                    <>
                        <BadgeCheck className="mx-auto size-12 text-leaf" />
                        <h1 className="mt-3 text-xl font-extrabold">{t('invoice.valid')}</h1>
                        <dl className="mt-6 space-y-3 text-left text-sm">
                            <div className="flex justify-between"><dt>{t('invoice.code')}</dt><dd className="font-mono font-bold">{order.code}</dd></div>
                            <div className="flex justify-between"><dt>{t('invoice.date')}</dt><dd>{formatDate(order.created_at, locale)}</dd></div>
                            <div className="flex justify-between"><dt>{t('checkout.total')}</dt><dd className="price font-bold">{rupiah(order.grand_total)}</dd></div>
                            <div className="flex justify-between"><dt>Status</dt><dd><StatusBadge status={order.status} /></dd></div>
                        </dl>
                    </>
                ) : (
                    <>
                        <CircleAlert className="mx-auto size-12 text-destructive" />
                        <h1 className="mt-3 text-xl font-extrabold">{t('invoice.invalid')}</h1>
                        <p className="mt-2 text-sm text-muted-foreground">{t('invoice.invalid_body', { code })}</p>
                    </>
                )}
            </div>
        </div>
    );
}
