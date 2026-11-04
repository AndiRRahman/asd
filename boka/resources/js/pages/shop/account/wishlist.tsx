import { Link } from '@inertiajs/react';
import { Heart } from 'lucide-react';
import { EmptyState } from '@/components/shop/bits';
import { ProductGrid } from '@/components/shop/product-card';
import { Seo } from '@/components/shop/seo';
import { Button } from '@/components/ui/button';
import { useLocalePath, useT } from '@/lib/i18n';
import type { ProductCard } from '@/types/boka';

export default function Wishlist({ products }: { products: ProductCard[] }) {
    const t = useT();
    const localePath = useLocalePath();

    return (
        <div className="mx-auto max-w-7xl px-4 py-6">
            <Seo title={t('nav.wishlist')} />
            <h1 className="mb-2 text-2xl font-extrabold">{t('nav.wishlist')}</h1>
            <p className="mb-6 text-sm text-muted-foreground">{t('wishlist.note')}</p>
            {products.length === 0 ? (
                <EmptyState icon={Heart} title={t('wishlist.empty')}>
                    <Button asChild><Link href={localePath('/produk')}>{t('cart.browse')}</Link></Button>
                </EmptyState>
            ) : (
                <ProductGrid products={products} />
            )}
        </div>
    );
}
