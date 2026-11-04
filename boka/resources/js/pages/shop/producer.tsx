import { MapPin, Phone } from 'lucide-react';
import { ProducerBadge } from '@/components/shop/bits';
import { Walasuji } from '@/components/shop/brand';
import { ProductGrid } from '@/components/shop/product-card';
import { Seo } from '@/components/shop/seo';
import { useT } from '@/lib/i18n';
import type { ProductCard } from '@/types/boka';

type Props = {
    producer: { name: string; slug: string; type: 'karang_taruna' | 'umkm'; village: string; district: string; story: string | null; photo: string | null; contact: string | null };
    products: ProductCard[];
};

export default function Producer({ producer, products }: Props) {
    const t = useT();

    return (
        <>
            <Seo title={producer.name} description={producer.story} image={producer.photo} path={`/produsen/${producer.slug}`} />
            <section className="relative overflow-hidden bg-hero text-hero-foreground">
                <Walasuji className="text-hero-foreground opacity-[0.08]" id="walasuji-producer" />
                <div className="relative mx-auto grid max-w-7xl gap-8 px-4 py-12 md:grid-cols-[1fr_280px]">
                    <div className="space-y-4">
                        <ProducerBadge type={producer.type} />
                        <h1 className="text-3xl font-extrabold tracking-tight sm:text-4xl">{producer.name}</h1>
                        <p className="flex items-center gap-2 text-hero-foreground/80"><MapPin className="size-4" /> {t('common.village_district', { village: producer.village, district: producer.district })}</p>
                        {producer.story && <p className="max-w-2xl text-lg leading-relaxed text-hero-foreground/90">{producer.story}</p>}
                        {producer.contact && <p className="flex items-center gap-2 text-sm text-hero-foreground/70"><Phone className="size-4" /> {producer.contact}</p>}
                    </div>
                    {producer.photo && <img src={producer.photo} alt={producer.name} className="aspect-square w-full rounded-2xl object-cover" />}
                </div>
            </section>
            <div className="mx-auto max-w-7xl px-4 py-8">
                <h2 className="mb-4 text-xl font-extrabold">{t('producer.products', { count: products.length })}</h2>
                <ProductGrid products={products} />
            </div>
        </>
    );
}
