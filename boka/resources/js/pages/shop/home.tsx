import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Package, ShieldCheck, Truck, Users } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Walasuji } from '@/components/shop/brand';
import { ProductGrid } from '@/components/shop/product-card';
import { Section } from '@/components/shop/section';
import { Seo } from '@/components/shop/seo';
import { useLocalePath, useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { ProductCard } from '@/types/boka';

type Banner = {
    id: number;
    title: string;
    subtitle: string | null;
    link: string | null;
    image_desktop: string | null;
    image_mobile: string | null;
};

type Props = {
    banners: Banner[];
    products: ProductCard[];
};

/** Hero dinamis dari database panel Admin, dengan fallback elegan jika belum ada banner. */
function Hero({ banners }: { banners: Banner[] }) {
    const t = useT();
    const localePath = useLocalePath();
    const [index, setIndex] = useState(0);

    useEffect(() => {
        if (banners.length < 2) {
            return;
        }

        const id = window.setInterval(() => setIndex((i) => (i + 1) % banners.length), 6500);

        return () => window.clearInterval(id);
    }, [banners.length]);

    const banner = banners[index] ?? {
        id: 0,
        title: 'Boka · Bone Karya',
        subtitle: 'Toko Daring Karang Taruna & UMKM Kabupaten Bone',
        link: '/produk',
        image_desktop: null,
        image_mobile: null,
    };

    return (
        <section className="mx-auto max-w-7xl px-4 pt-4 sm:pt-6" aria-roledescription="carousel" aria-label={t('home.hero_label')}>
            <div className="relative isolate overflow-hidden rounded-2xl bg-hero text-hero-foreground">
                <Walasuji className="text-hero-foreground opacity-[0.08]" id="walasuji-hero" />
                <div className="absolute -top-24 -right-24 size-80 rotate-45 rounded-[3rem] border-[28px] border-gold/25" aria-hidden="true" />
                <div className="absolute -bottom-28 right-40 hidden size-56 rotate-45 rounded-[2.5rem] bg-primary/40 lg:block" aria-hidden="true" />
                {banner.image_desktop && (
                    <picture className="absolute inset-0 -z-10">
                        {banner.image_mobile && <source media="(max-width: 640px)" srcSet={banner.image_mobile} />}
                        <img src={banner.image_desktop} alt="" fetchPriority="high" className="size-full object-cover opacity-40" />
                    </picture>
                )}
                <div className="relative grid min-h-[300px] items-center gap-8 px-6 py-10 sm:min-h-[360px] sm:px-12 lg:grid-cols-[1.4fr_1fr]">
                    <div key={banner.id} className="max-w-xl animate-rise">
                        <p className="mb-3 inline-flex items-center gap-2 rounded-full bg-hero-foreground/10 px-3 py-1 text-xs font-semibold tracking-wide">
                            <span className="size-1.5 rotate-45 bg-gold" aria-hidden="true" /> {t('home.hero_kicker')}
                        </p>
                        <h1 className="text-3xl leading-tight font-extrabold tracking-tight text-balance sm:text-5xl">{banner.title}</h1>
                        {banner.subtitle && <p className="mt-4 max-w-lg text-base text-hero-foreground/80 sm:text-lg">{banner.subtitle}</p>}
                        {banner.link && (
                            <Link href={localePath(banner.link)} className="mt-6 inline-flex h-12 items-center rounded-full bg-gold px-7 font-bold text-gold-foreground transition-transform hover:-translate-y-0.5">
                                {t('home.hero_cta')}
                            </Link>
                        )}
                    </div>
                    <ul className="hidden gap-3 text-sm lg:grid">
                        {[
                            { icon: Users, text: t('home.trust_producers') },
                            { icon: ShieldCheck, text: t('home.trust_payment') },
                            { icon: Truck, text: t('home.trust_shipping') },
                        ].map(({ icon: Icon, text }) => (
                            <li key={text} className="flex items-center gap-3 rounded-xl bg-hero-foreground/[0.07] p-4 backdrop-blur-sm">
                                <Icon className="size-5 shrink-0 text-gold" aria-hidden="true" /> {text}
                            </li>
                        ))}
                    </ul>
                </div>
                {banners.length > 1 && (
                    <div className="absolute right-4 bottom-4 flex items-center gap-2">
                        <button type="button" onClick={() => setIndex((index - 1 + banners.length) % banners.length)} className="flex size-11 items-center justify-center rounded-full bg-hero-foreground/10 hover:bg-hero-foreground/20" aria-label={t('common.previous')}>
                            <ChevronLeft className="size-5" />
                        </button>
                        <div className="flex gap-1.5">
                            {banners.map((b, i) => (
                                <span key={b.id} className={cn('h-1.5 rounded-full bg-hero-foreground transition-all', i === index ? 'w-6' : 'w-1.5 opacity-40')} />
                            ))}
                        </div>
                        <button type="button" onClick={() => setIndex((index + 1) % banners.length)} className="flex size-11 items-center justify-center rounded-full bg-hero-foreground/10 hover:bg-hero-foreground/20" aria-label={t('common.next')}>
                            <ChevronRight className="size-5" />
                        </button>
                    </div>
                )}
            </div>
        </section>
    );
}

export default function Home(props: Props) {
    const t = useT();
    const localePath = useLocalePath();

    return (
        <>
            <Seo title={t('home.meta_title')} description={t('home.meta_description')} path="/" />
            <Hero banners={props.banners} />

            <Section title={t('home.catalog_title', 'Produk Pilihan')} href={localePath('/produk')}>
                {props.products.length > 0 ? (
                    <ProductGrid products={props.products} priorityFirst={4} />
                ) : (
                    <div className="rounded-2xl border border-dashed bg-card/50 p-12 text-center">
                        <Package className="mx-auto size-10 text-muted-foreground/60" />
                        <h3 className="mt-3 text-base font-bold">Belum Ada Produk</h3>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Silakan tambahkan produk baru melalui <Link href="/panel/produk" className="font-semibold text-primary underline">Panel Admin</Link>.
                        </p>
                    </div>
                )}
            </Section>
        </>
    );
}
