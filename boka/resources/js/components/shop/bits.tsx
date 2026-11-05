import { Coffee, Cookie, Fish, Gem, Leaf, Package, Scroll, Star } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import { discountPercent, rupiah } from '@/lib/format';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { ImageData } from '@/types/boka';

const CATEGORY_ICONS: Record<string, LucideIcon> = { coffee: Coffee, scroll: Scroll, fish: Fish, cookie: Cookie, gem: Gem, leaf: Leaf };

export function CategoryIcon({ name, className }: { name?: string | null; className?: string }) {
    const Icon = (name && CATEGORY_ICONS[name]) || Package;

    return <Icon className={className} aria-hidden="true" />;
}

/**
 * Gambar dengan width/height agar tata letak tidak bergeser; warna dominan menjadi latar selama memuat (12.8).
 */
export function ProductImage({
    image,
    alt,
    priority = false,
    className,
}: {
    image: ImageData | null;
    alt: string;
    priority?: boolean;
    className?: string;
}) {
    if (!image) {
        return (
            <div className={cn('flex aspect-square items-center justify-center bg-muted text-muted-foreground', className)}>
                <Package className="size-10" aria-hidden="true" />
            </div>
        );
    }

    return (
        <img
            src={image.url}
            alt={alt}
            width={image.width}
            height={image.height}
            loading={priority ? 'eager' : 'lazy'}
            fetchPriority={priority ? 'high' : 'auto'}
            decoding="async"
            style={{ backgroundColor: image.color ?? undefined }}
            className={cn('aspect-square w-full object-cover', className)}
        />
    );
}

export function Price({
    price,
    compare,
    size = 'md',
    showDiscount = false,
    className,
}: {
    price: number;
    compare?: number | null;
    size?: 'sm' | 'md' | 'lg';
    showDiscount?: boolean;
    className?: string;
}) {
    const percent = showDiscount ? discountPercent(price, compare ?? null) : null;

    return (
        <div className={cn('flex flex-wrap items-baseline gap-x-2 gap-y-0.5', className)}>
            <span
                className={cn(
                    'price font-bold text-foreground',
                    size === 'sm' && 'text-sm',
                    size === 'md' && 'text-base',
                    size === 'lg' && 'text-3xl font-extrabold',
                )}
            >
                {rupiah(price)}
            </span>
            {percent && (
                <>
                    <s className="price text-xs text-muted-foreground">{rupiah(compare)}</s>
                    <span className="rounded bg-gold px-1 py-px text-[11px] font-bold text-gold-foreground">−{percent}%</span>
                </>
            )}
        </div>
    );
}

export function Rating({ value, count, className }: { value: number | null; count?: number; className?: string }) {
    const t = useT();

    if (!value) {
        return null;
    }

    return (
        <span className={cn('inline-flex items-center gap-1 text-xs text-muted-foreground', className)}>
            <Star className="size-3.5 fill-gold text-gold" aria-hidden="true" />
            <span className="font-semibold text-foreground">{value.toFixed(1)}</span>
            {count !== undefined && <span>({count})</span>}
            <span className="sr-only">{t('product.rating_label', { value: value.toFixed(1) })}</span>
        </span>
    );
}

export function Stars({ value, size = 'size-4' }: { value: number; size?: string }) {
    return (
        <span className="inline-flex gap-0.5" aria-label={`${value}/5`}>
            {[1, 2, 3, 4, 5].map((i) => (
                <Star key={i} aria-hidden="true" className={cn(size, i <= Math.round(value) ? 'fill-gold text-gold' : 'text-border')} />
            ))}
        </span>
    );
}

/**
 * Sisa waktu dalam milidetik, diperbarui tiap detik. Bernilai null sampai halaman tampil di browser,
 * agar render server (SSR) dan render pertama browser sama persis (tanpa galat hidrasi React).
 */
export function useCountdown(target: string | null): number | null {
    const [remaining, setRemaining] = useState<number | null>(null);

    useEffect(() => {
        if (!target) {
            return;
        }

        const tick = () => setRemaining(Math.max(0, new Date(target).getTime() - Date.now()));
        tick();
        const id = window.setInterval(tick, 1000);

        return () => window.clearInterval(id);
    }, [target]);

    return remaining === null ? null : Math.max(0, remaining);
}

/** Jam, menit, detik dua digit; "--" selama sisa waktu belum dihitung. */
export function splitDuration(ms: number | null) {
    if (ms === null) {
        return { hours: '--', minutes: '--', seconds: '--' };
    }

    const total = Math.floor(ms / 1000);
    const pad = (n: number) => String(n).padStart(2, '0');

    return {
        hours: pad(Math.floor(total / 3600)),
        minutes: pad(Math.floor((total % 3600) / 60)),
        seconds: pad(total % 60),
    };
}

export function CountdownBoxes({ target, className }: { target: string; className?: string }) {
    const { hours, minutes, seconds } = splitDuration(useCountdown(target));
    const cell = 'price min-w-9 rounded-md bg-foreground px-1.5 py-1 text-center text-sm font-bold text-background';

    return (
        <span className={cn('inline-flex items-center gap-1', className)} role="timer">
            <span className={cell}>{hours}</span>:
            <span className={cell}>{minutes}</span>:
            <span className={cell}>{seconds}</span>
        </span>
    );
}

export function EmptyState({ icon: Icon = Package, title, body, children }: { icon?: LucideIcon; title: string; body?: string; children?: React.ReactNode }) {
    return (
        <div className="relative flex flex-col items-center gap-3 overflow-hidden rounded-2xl border border-dashed bg-card px-6 py-14 text-center">
            <div className="flex size-16 rotate-45 items-center justify-center rounded-2xl bg-primary-soft">
                <Icon className="size-7 -rotate-45 text-primary" aria-hidden="true" />
            </div>
            <h2 className="mt-2 text-lg font-bold">{title}</h2>
            {body && <p className="max-w-sm text-sm text-muted-foreground">{body}</p>}
            {children}
        </div>
    );
}

export function ProducerBadge({ type }: { type: 'karang_taruna' | 'umkm' }) {
    return (
        <span
            className={cn(
                'inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold',
                type === 'karang_taruna' ? 'bg-primary-soft text-primary' : 'bg-gold-soft text-foreground',
            )}
        >
            {type === 'karang_taruna' ? 'Karang Taruna' : 'UMKM'}
        </span>
    );
}
