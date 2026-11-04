import { Link } from '@inertiajs/react';
import { PackageX } from 'lucide-react';
import { EmptyState } from '@/components/shop/bits';
import { Seo } from '@/components/shop/seo';
import { Button } from '@/components/ui/button';
import { useLocalePath, useT } from '@/lib/i18n';

/** Tautan lama ke produk Nonaktif atau Arsip menampilkan "produk tidak tersedia" (4.1). */
export default function ProductUnavailable({ name }: { name: string }) {
    const t = useT();
    const localePath = useLocalePath();

    return (
        <div className="mx-auto max-w-2xl px-4 py-12">
            <Seo title={t('product.unavailable_title')} />
            <EmptyState icon={PackageX} title={t('product.unavailable_title')} body={t('product.unavailable_body', { name })}>
                <Button asChild className="mt-2"><Link href={localePath('/produk')}>{t('cart.browse')}</Link></Button>
            </EmptyState>
        </div>
    );
}
