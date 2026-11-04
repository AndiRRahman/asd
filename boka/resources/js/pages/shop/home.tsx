import { Link, usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, MapPin, Quote, ShieldCheck, Truck, Users, Zap } from 'lucide-react';
import { useEffect, useState } from 'react';
import { CategoryIcon, CountdownBoxes, ProducerBadge, Stars } from '@/components/shop/bits';
import { Walasuji } from '@/components/shop/brand';
import { ProductGrid } from '@/components/shop/product-card';
import { Reveal } from '@/components/shop/reveal';
import { Section } from '@/components/shop/section';
import { Seo } from '@/components/shop/seo';
import { formatDate } from '@/lib/format';
import { useLocalePath, useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { CategoryRef, PostCard, ProductCard, Review } from '@/types/boka';

type Banner = { id: number; title: string; subtitle: string | null; link: string | null; image_desktop: string | null; image_mobile: string | null };
type ProducerStory = { name: string; slug: string; type: 'karang_taruna' | 'umkm'; village: string; district: string; story: string | null; products_count: number };

type Props = {
    banners: Banner[];
    popularCategories: CategoryRef[];
    exploreCategories: CategoryRef[];
    flashSale: { ends_at: string; name: string; products: ProductCard[] } | null;
    discounted: ProductCard[];
    bestSellers: ProductCard[];
    newest: ProductCard[];
    related: ProductCard[];
    hiddenGems: ProductCard[];
    producers: ProducerStory[];
    posts: PostCard[];
    reviews: Review[];
};

/** Hero berganti otomatis; jadwal tayang diatur dari panel Admin (3.1). */
function Hero({ banners }: { banners: Banner[] }) {
    const t = useT();
    const localePath = useLocalePath();
    const [index, setIndex] = useState(0);
    const banner = banners[index];

    useEffect(() => {
        if (banners.length < 2) {
            return;
        }

        const id = window.setInterval(() => setIndex((i) => (i + 1) % banners.length), 6500);

        return () => window.clearInterval(id);
    }, [banners.length]);

    if (!banner) {
        return null;
    }

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

function CategoryChips({ categories }: { categories: CategoryRef[] }) {
    const localePath = useLocalePath();

    return (
        <div className="grid grid-cols-4 gap-2 sm:grid-cols-8">
            {categories.map((c) => (
                <Link key={c.slug} href={localePath(`/kategori/${c.slug}`)} className="group flex flex-col items-center gap-2 rounded-xl p-2 text-center text-xs font-semibold hover:bg-card sm:text-sm">
                    <span className="flex size-14 rotate-45 items-center justify-center rounded-2xl border bg-card transition-colors group-hover:border-primary group-hover:bg-primary-soft sm:size-16">
                        <CategoryIcon name={c.icon} className="size-6 -rotate-45 text-primary" />
                    </span>
                    <span className="mt-1 line-clamp-2">{c.name}</span>
                </Link>
            ))}
        </div>
    );
}

export default function Home(props: Props) {
    const t = useT();
    const localePath = useLocalePath();
    const { locale } = usePage().props;

    return (
        <>
            <Seo title={t('home.meta_title')} description={t('home.meta_description')} path="/" />
            <Hero banners={props.banners} />

            {props.popularCategories.length > 0 && (
                <Section title={t('home.popular_categories')} kicker={t('home.popular_kicker')}>
                    <CategoryChips categories={props.popularCategories} />
                </Section>
            )}

            {props.flashSale && (
                <Section
                    title={props.flashSale.name}
                    kicker={t('home.flash_kicker')}
                    href={localePath('/produk?sort=terlaris')}
                    action={
                        <div className="flex items-center gap-2 text-sm font-semibold">
                            <Zap className="size-4 text-gold" aria-hidden="true" />
                            <span className="hidden sm:inline">{t('home.flash_ends')}</span>
                            <CountdownBoxes target={props.flashSale.ends_at} />
                        </div>
                    }
                >
                    <div className="rounded-2xl bg-gold-soft p-3 sm:p-4">
                        <ProductGrid products={props.flashSale.products} />
                    </div>
                </Section>
            )}

            {props.related.length > 0 && (
                <Section title={t('home.related')} kicker={t('home.related_kicker')}>
                    <ProductGrid products={props.related} />
                </Section>
            )}

            {props.discounted.length > 0 && (
                <Section title={t('home.discounted')} href={localePath('/produk')}>
                    <ProductGrid products={props.discounted} />
                </Section>
            )}

            <Section title={t('home.best_sellers')} href={localePath('/produk?sort=terlaris')}>
                <ProductGrid products={props.bestSellers} priorityFirst={2} />
            </Section>

            {/* Cerita produsen: kartu Karang Taruna dan UMKM beserta desa dan kecamatannya. */}
            <section className="relative mt-6 overflow-hidden bg-card py-12">
                <Walasuji className="text-primary opacity-[0.035]" id="walasuji-producers" />
                <div className="relative mx-auto max-w-7xl px-4">
                    <p className="mb-1 text-xs font-bold tracking-[0.16em] text-primary uppercase">{t('home.producers_kicker')}</p>
                    <h2 className="max-w-2xl text-2xl font-extrabold tracking-tight sm:text-3xl">{t('home.producers_title')}</h2>
                    <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {props.producers.map((p, i) => (
                            <Reveal key={p.slug} delay={i * 60}>
                                <Link href={localePath(`/produsen/${p.slug}`)} className="flex h-full flex-col gap-3 rounded-2xl border bg-background p-5 transition-colors hover:border-primary">
                                    <div className="flex items-center justify-between gap-2">
                                        <ProducerBadge type={p.type} />
                                        <span className="text-xs text-muted-foreground">{t('home.products_count', { count: p.products_count })}</span>
                                    </div>
                                    <h3 className="text-lg font-bold">{p.name}</h3>
                                    <p className="line-clamp-3 text-sm text-muted-foreground">{p.story}</p>
                                    <p className="mt-auto flex items-center gap-1 text-xs font-semibold text-foreground/80">
                                        <MapPin className="size-3.5 text-primary" aria-hidden="true" /> {t('common.village_district', { village: p.village, district: p.district })}
                                    </p>
                                </Link>
                            </Reveal>
                        ))}
                    </div>
                </div>
            </section>

            {(props.exploreCategories.length > 0 || props.hiddenGems.length > 0) && (
                <Section title={t('home.explore')} kicker={t('home.explore_kicker')}>
                    <div className="grid gap-6 lg:grid-cols-[1fr_2fr]">
                        <div className="flex flex-wrap content-start gap-2">
                            {props.exploreCategories.map((c) => (
                                <Link key={c.slug} href={localePath(`/kategori/${c.slug}`)} className="flex items-center gap-2 rounded-full border bg-card px-4 py-2.5 text-sm font-semibold hover:border-primary">
                                    <CategoryIcon name={c.icon} className="size-4 text-primary" /> {c.name}
                                </Link>
                            ))}
                            <p className="mt-2 w-full text-sm text-muted-foreground">{t('home.explore_note')}</p>
                        </div>
                        <ProductGrid products={props.hiddenGems} className="lg:grid-cols-4 xl:grid-cols-4" />
                    </div>
                </Section>
            )}

            <Section title={t('home.newest')} href={localePath('/produk?sort=terbaru')}>
                <ProductGrid products={props.newest} />
            </Section>

            {props.posts.length > 0 && (
                <Section title={t('home.posts')} kicker={t('home.posts_kicker')} href={localePath('/kabar')}>
                    <div className="grid gap-4 md:grid-cols-3">
                        {props.posts.map((post) => (
                            <Link key={post.slug} href={localePath(`/kabar/${post.slug}`)} className="group overflow-hidden rounded-2xl border bg-card">
                                <div className="relative aspect-[16/9] overflow-hidden bg-primary-soft">
                                    {post.cover ? <img src={post.cover} alt="" loading="lazy" className="size-full object-cover" /> : <Walasuji className="text-primary opacity-20" id={`walasuji-${post.slug}`} />}
                                    <span className="absolute top-3 left-3 rounded-full bg-background/90 px-2.5 py-1 text-[11px] font-bold uppercase">{t(`post.type_${post.type}`)}</span>
                                </div>
                                <div className="space-y-2 p-4">
                                    <p className="text-xs text-muted-foreground">{formatDate(post.published_at, locale)}{post.location && ` · ${post.location}`}</p>
                                    <h3 className="font-bold group-hover:text-primary">{post.title}</h3>
                                    <p className="line-clamp-2 text-sm text-muted-foreground">{post.excerpt}</p>
                                </div>
                            </Link>
                        ))}
                    </div>
                </Section>
            )}

            {props.reviews.length > 0 && (
                <Section title={t('home.reviews')} kicker={t('home.reviews_kicker')}>
                    <div className="flex snap-x gap-4 overflow-x-auto pb-2">
                        {props.reviews.map((r) => (
                            <figure key={r.id} className="w-72 shrink-0 snap-start rounded-2xl border bg-card p-5">
                                <Quote className="size-6 text-gold" aria-hidden="true" />
                                <blockquote className="mt-2 line-clamp-4 text-sm">{r.body}</blockquote>
                                <figcaption className="mt-4 flex items-center justify-between gap-2 text-xs">
                                    <span>
                                        <span className="block font-bold">{r.author}</span>
                                        {r.product && <Link href={localePath(`/produk/${r.product.slug}`)} className="text-muted-foreground hover:text-primary">{r.product.name}</Link>}
                                    </span>
                                    <Stars value={r.rating} size="size-3.5" />
                                </figcaption>
                            </figure>
                        ))}
                    </div>
                </Section>
            )}
        </>
    );
}
