import { Link } from '@inertiajs/react';
import { Zap } from 'lucide-react';
import { useLocalePath, useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { ProductCard as ProductCardType } from '@/types/boka';
import { Price, ProductImage, Rating } from './bits';

/**
 * Kartu produk seragam (12.2): foto 1:1, nama 2 baris, harga, harga coret, nama produsen, rating.
 */
export function ProductCard({ product, priority = false }: { product: ProductCardType; priority?: boolean }) {
    const t = useT();
    const localePath = useLocalePath();

    return (
        <Link
            href={localePath(`/produk/${product.slug}`)}
            className="group flex flex-col overflow-hidden rounded-xl border bg-card transition-shadow hover:shadow-[0_8px_30px_-12px_rgb(47_107_79/0.3)] focus-visible:shadow-lg"
        >
            <div className="relative overflow-hidden">
                <ProductImage image={product.image} alt={product.name} priority={priority} className="transition-transform duration-300 group-hover:scale-[1.03]" />
                {product.is_flash && (
                    <span className="absolute top-2 left-2 inline-flex items-center gap-1 rounded-full bg-gold px-2 py-0.5 text-[11px] font-bold text-gold-foreground">
                        <Zap className="size-3" aria-hidden="true" /> {t('product.flash')}
                    </span>
                )}
                {!product.in_stock && (
                    <span className="absolute inset-x-0 bottom-0 bg-foreground/80 py-1 text-center text-xs font-semibold text-background">{t('product.sold_out')}</span>
                )}
            </div>
            <div className="flex flex-1 flex-col gap-1.5 p-3">
                <h3 className="line-clamp-2 min-h-[2.5rem] text-sm leading-5 font-semibold">{product.name}</h3>
                <Price price={product.price} compare={product.compare_price} size="sm" />
                <div className="mt-auto flex items-center justify-between gap-2 pt-1">
                    <span className="truncate text-xs text-muted-foreground">{product.producer?.name}</span>
                    <Rating value={product.rating} />
                </div>
                {product.sold > 0 && <span className="text-[11px] text-muted-foreground">{t('product.sold_count', { count: product.sold })}</span>}
            </div>
        </Link>
    );
}

export function ProductGrid({ products, className, priorityFirst = 0 }: { products: ProductCardType[]; className?: string; priorityFirst?: number }) {
    return (
        <div className={cn('grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4 xl:grid-cols-5', className)}>
            {products.map((product, index) => (
                <ProductCard key={product.id} product={product} priority={index < priorityFirst} />
            ))}
        </div>
    );
}

/** Kerangka berbentuk kartu saat memuat, bukan pemutar (12.3). */
export function ProductGridSkeleton({ count = 10 }: { count?: number }) {
    return (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4 xl:grid-cols-5">
            {Array.from({ length: count }).map((_, i) => (
                <div key={i} className="overflow-hidden rounded-xl border bg-card">
                    <div className="aspect-square animate-pulse bg-muted" />
                    <div className="space-y-2 p-3">
                        <div className="h-3 w-4/5 animate-pulse rounded bg-muted" />
                        <div className="h-3 w-1/2 animate-pulse rounded bg-muted" />
                    </div>
                </div>
            ))}
        </div>
    );
}
