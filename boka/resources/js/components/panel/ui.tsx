import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import PanelLayout from '@/layouts/panel-layout';
import { cn } from '@/lib/utils';

export function PanelPage({ title, description, actions, children }: { title: string; description?: string; actions?: React.ReactNode; children: React.ReactNode }) {
    return (
        <PanelLayout>
        <div className="mx-auto max-w-7xl space-y-6">
            <Head title={`${title} · Panel`} />
            <div className="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-extrabold tracking-tight">{title}</h1>
                    {description && <p className="mt-1 max-w-3xl text-sm text-muted-foreground">{description}</p>}
                </div>
                {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
            </div>
            {children}
        </div>
        </PanelLayout>
    );
}

export function Card({ title, children, className, actions }: { title?: string; children: React.ReactNode; className?: string; actions?: React.ReactNode }) {
    return (
        <section className={cn('rounded-2xl border bg-card p-5', className)}>
            {(title || actions) && (
                <div className="mb-4 flex items-center justify-between gap-2">
                    {title && <h2 className="font-extrabold">{title}</h2>}
                    {actions}
                </div>
            )}
            {children}
        </section>
    );
}

export function Stat({ label, value, tone = 'default' }: { label: string; value: React.ReactNode; tone?: 'default' | 'warn' | 'good' }) {
    return (
        <div className={cn('rounded-2xl border bg-card p-5', tone === 'warn' && 'border-gold bg-gold-soft', tone === 'good' && 'bg-leaf-soft')}>
            <p className="text-sm text-muted-foreground">{label}</p>
            <p className="price mt-1 text-2xl font-extrabold">{value}</p>
        </div>
    );
}

export function Table({ head, children, empty }: { head: React.ReactNode[]; children: React.ReactNode; empty?: string }) {
    const hasRows = Array.isArray(children) ? children.length > 0 : Boolean(children);

    return (
        <div className="overflow-x-auto rounded-2xl border bg-card">
            <table className="w-full text-sm">
                <thead className="border-b bg-muted/50 text-left text-xs text-muted-foreground uppercase">
                    <tr>{head.map((h, i) => <th key={i} className="px-4 py-3 font-semibold whitespace-nowrap">{h}</th>)}</tr>
                </thead>
                <tbody className="divide-y">
                    {hasRows ? children : <tr><td colSpan={head.length} className="px-4 py-10 text-center text-muted-foreground">{empty ?? 'Belum ada data.'}</td></tr>}
                </tbody>
            </table>
        </div>
    );
}

export function Pager({ current, last }: { current: number; last: number }) {
    if (last <= 1) {
        return null;
    }

    const go = (page: number) => router.get(window.location.pathname, { ...Object.fromEntries(new URLSearchParams(window.location.search)), page }, { preserveScroll: true });

    return (
        <div className="flex items-center justify-center gap-2">
            <Button variant="outline" size="sm" disabled={current <= 1} onClick={() => go(current - 1)}>Sebelumnya</Button>
            <span className="text-sm text-muted-foreground">Halaman {current} dari {last}</span>
            <Button variant="outline" size="sm" disabled={current >= last} onClick={() => go(current + 1)}>Berikutnya</Button>
        </div>
    );
}

export function Pill({ children, tone = 'muted' }: { children: React.ReactNode; tone?: 'muted' | 'warn' | 'good' | 'bad' | 'primary' }) {
    return (
        <span className={cn('inline-flex rounded-full px-2.5 py-0.5 text-xs font-bold whitespace-nowrap', {
            'bg-muted text-muted-foreground': tone === 'muted',
            'bg-gold-soft text-foreground': tone === 'warn',
            'bg-leaf-soft text-leaf': tone === 'good',
            'bg-destructive/10 text-destructive': tone === 'bad',
            'bg-primary-soft text-primary': tone === 'primary',
        })}>
            {children}
        </span>
    );
}

export const STATUS_LABEL: Record<string, string> = {
    menunggu_pembayaran: 'Menunggu pembayaran',
    menunggu_verifikasi: 'Menunggu verifikasi',
    diproses: 'Diproses',
    dikirim: 'Dikirim',
    selesai: 'Selesai',
    kedaluwarsa: 'Kedaluwarsa',
    dibatalkan: 'Dibatalkan',
};

export function OrderStatusPill({ status }: { status: string }) {
    const tone = ({ menunggu_pembayaran: 'warn', menunggu_verifikasi: 'warn', diproses: 'primary', dikirim: 'primary', selesai: 'good' } as const)[status as 'selesai'] ?? 'muted';

    return <Pill tone={tone}>{STATUS_LABEL[status] ?? status}</Pill>;
}

/** Konfirmasi sebelum aksi yang tidak bisa dibatalkan (12.5). */
export function ConfirmButton({ label, title, body, onConfirm, variant = 'default', disabled }: { label: React.ReactNode; title: string; body: string; onConfirm: () => void; variant?: 'default' | 'destructive' | 'outline'; disabled?: boolean }) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <Button variant={variant} disabled={disabled} onClick={() => setOpen(true)}>{label}</Button>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        <DialogDescription>{body}</DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setOpen(false)}>Batal</Button>
                        <Button variant={variant === 'outline' ? 'default' : variant} onClick={() => { setOpen(false); onConfirm(); }}>Ya, lanjutkan</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

export function SearchForm({ value, placeholder, extra }: { value?: string | null; placeholder: string; extra?: Record<string, string | null | undefined> }) {
    const [q, setQ] = useState(value ?? '');

    return (
        <form className="flex gap-2" onSubmit={(e) => { e.preventDefault(); router.get(window.location.pathname, { ...extra, q }, { preserveState: true }); }}>
            <input value={q} onChange={(e) => setQ(e.target.value)} placeholder={placeholder} className="h-11 w-64 rounded-lg border border-input bg-card px-3" />
            <Button type="submit" variant="outline">Cari</Button>
        </form>
    );
}

export function LinkButton({ href, children, variant = 'default' }: { href: string; children: React.ReactNode; variant?: 'default' | 'outline' }) {
    return <Button asChild variant={variant}><Link href={href}>{children}</Link></Button>;
}

export const fieldClass = 'h-11 w-full rounded-lg border border-input bg-card px-3';
