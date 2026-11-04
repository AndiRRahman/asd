import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { OrderStatus } from '@/types/boka';

const TONE: Record<OrderStatus, string> = {
    menunggu_pembayaran: 'bg-gold-soft text-foreground',
    menunggu_verifikasi: 'bg-gold-soft text-foreground',
    diproses: 'bg-primary-soft text-primary',
    dikirim: 'bg-primary-soft text-primary',
    selesai: 'bg-leaf-soft text-leaf',
    kedaluwarsa: 'bg-muted text-muted-foreground',
    dibatalkan: 'bg-muted text-muted-foreground',
};

export function StatusBadge({ status }: { status: OrderStatus }) {
    const t = useT();

    return <span className={cn('rounded-full px-2.5 py-0.5 text-xs font-bold', TONE[status])}>{t(`status.${status}`)}</span>;
}
