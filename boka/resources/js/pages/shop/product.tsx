import { Deferred, Link, router, usePage } from '@inertiajs/react';
import { Bell, Heart, MapPin, Minus, Plus, Share2, ShoppingBag, Store, Truck, Zap } from 'lucide-react';
import { useRef, useState } from 'react';
import { CountdownBoxes, Price, ProducerBadge, ProductImage, Stars } from '@/components/shop/bits';
import { ProductGrid, ProductGridSkeleton } from '@/components/shop/product-card';
import { Seo } from '@/components/shop/seo';
import { Button } from '@/components/ui/button';
import { formatDate, grams } from '@/lib/format';
import { useLocalePath, useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { ProductCard, ProductDetail, Review } from '@/types/boka';

type Props = {
    product: ProductDetail;
    reviews: Review[];
    ratingDistribution: Record<string, number>;
    related: ProductCard[];
    forYou?: ProductCard[];
    wishlisted: boolean;
    inCart: Record<string, number>;
    shareUrl: string;
};

/** Zoom mengikuti kursor di desktop; di HP foto digeser (scroll-snap). */
function Gallery({ product }: { product: ProductDetail }) {
    const [active, setActive] = useState(0);
    const [zoom, setZoom] = useState<{ x: number; y: number } | null>(null);
    const images = product.images.length ? product.images : [null];

    return (
        <div className="space-y-3">
            <div className="hidden overflow-hidden rounded-2xl border bg-card md:block" onMouseLeave={() => setZoom(null)} onMouseMove={(e) => {
                const rect = e.currentTarget.getBoundingClientRect();
                setZoom({ x: ((e.clientX - rect.left) / rect.width) * 100, y: ((e.clientY - rect.top) / rect.height) * 100 });
            }}>
                <div id="product-main-image" style={{ transform: zoom ? 'scale(1.8)' : undefined, transformOrigin: zoom ? `${zoom.x}% ${zoom.y}%` : undefined }} className="transition-transform duration-150">
                    <ProductImage image={images[active]} alt={product.name} priority />
                </div>
            </div>
            <div className="-mx-4 flex snap-x snap-mandatory overflow-x-auto md:hidden">
                {images.map((image, i) => (
                    <div key={i} className="w-full shrink-0 snap-center px-4">
                        <ProductImage image={image} alt={`${product.name} ${i + 1}`} priority={i === 0} className="rounded-2xl border" />
                    </div>
                ))}
            </div>
            {images.length > 1 && (
                <div className="hidden gap-2 md:flex">
                    {images.map((image, i) => (
                        <button key={i} type="button" onClick={() => setActive(i)} className={cn('w-20 overflow-hidden rounded-lg border-2', i === active ? 'border-primary' : 'border-transparent')} aria-label={`${i + 1}`}>
                            <ProductImage image={image} alt="" />
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}

/** Gambar produk "terbang" ke ikon keranjang. Hanya transform dan opacity, 300 ms (12.3). */
function flyToCart(from: HTMLElement | null) {
    const target = document.getElementById('cart-icon');
    if (!from || !target || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }
    const a = from.getBoundingClientRect();
    const b = target.getBoundingClientRect();
    const ghost = from.cloneNode(true) as HTMLElement;
    Object.assign(ghost.style, { position: 'fixed', left: `${a.left}px`, top: `${a.top}px`, width: `${a.width}px`, height: `${a.height}px`, zIndex: '60', pointerEvents: 'none', borderRadius: '16px', overflow: 'hidden' });
    document.body.appendChild(ghost);
    const dx = b.left + b.width / 2 - (a.left + a.width / 2);
    const dy = b.top + b.height / 2 - (a.top + a.height / 2);
    ghost.animate([{ transform: 'translate(0,0) scale(1)', opacity: 0.9 }, { transform: `translate(${dx}px, ${dy}px) scale(0.08)`, opacity: 0.2 }], { duration: 300, easing: 'cubic-bezier(.5,0,.75,0)' }).onfinish = () => ghost.remove();
}

export default function Product({ product, reviews, ratingDistribution, related, wishlisted, inCart, shareUrl }: Props) {
    const t = useT();
    const localePath = useLocalePath();
    const { auth, locale, store } = usePage<{ store?: { whatsapp?: string } }>().props;
    const [variantId, setVariantId] = useState(product.variants.find((v) => v.max_buyable > 0)?.id ?? product.variants[0]?.id);
    const variant = product.variants.find((v) => v.id === variantId) ?? product.variants[0];
    const already = inCart[String(variant?.id)] ?? 0;
    const maxAddable = Math.max(0, (variant?.max_buyable ?? 0) - already);
    const [qty, setQty] = useState(1);
    const [busy, setBusy] = useState(false);
    const [heartPop, setHeartPop] = useState(false);
    const imageRef = useRef<HTMLDivElement>(null);
    const totalReviews = Object.values(ratingDistribution).reduce((a, b) => a + b, 0);

    function addToCart() {
        if (!variant || maxAddable < 1) {
            return;
        }
        setBusy(true);
        router.post('/keranjang', { variant_id: variant.id, qty: Math.min(qty, maxAddable) }, {
            preserveScroll: true,
            onSuccess: () => {
                flyToCart(imageRef.current?.querySelector('img') ?? null);
                setQty(1);
            },
            onFinish: () => setBusy(false),
        });
    }

    function toggleWishlist() {
        if (!auth.user) {
            router.visit('/login');

            return;
        }
        setHeartPop(true);
        window.setTimeout(() => setHeartPop(false), 300);
        router.post(`/wishlist/${product.id}`, {}, { preserveScroll: true });
    }

    const shareText = `${product.name} — ${new Intl.NumberFormat('id-ID').format(variant?.price ?? product.price)} di e-TEJASEKU\n${shareUrl}`;

    const rawPhone = store?.whatsapp ?? '0812-0000-0000';
    let adminPhone = rawPhone.replace(/\D/g, '');
    if (adminPhone.startsWith('0')) {
        adminPhone = '62' + adminPhone.slice(1);
    }
    const tanyaAdminText = `Halo Admin e-TEJASEKU, saya ingin bertanya tentang produk *${product.name}*:\n${shareUrl}`;
    const tanyaAdminUrl = `https://wa.me/${adminPhone}?text=${encodeURIComponent(tanyaAdminText)}`;

    return (
        <>
            <Seo title={product.name} description={product.description?.slice(0, 160)} image={product.image?.url} path={`/produk/${product.slug}`} />
            <div className="mx-auto max-w-7xl px-4 py-6">
                <nav aria-label="Breadcrumb" className="mb-4 text-sm text-muted-foreground">
                    <Link href={localePath('/')} className="hover:text-primary">{t('nav.home')}</Link>
                    {product.category?.parent && <> / <Link href={localePath(`/kategori/${product.category.parent.slug}`)} className="hover:text-primary">{product.category.parent.name}</Link></>}
                    {product.category && <> / <Link href={localePath(`/kategori/${product.category.slug}`)} className="hover:text-primary">{product.category.name}</Link></>}
                </nav>

                <div className="grid gap-8 lg:grid-cols-[1.05fr_1fr]">
                    <div ref={imageRef}><Gallery product={product} /></div>

                    <div className="space-y-5">
                        <div>
                            {product.producer && <ProducerBadge type={product.producer.type} />}
                            <h1 className="mt-2 text-2xl leading-tight font-extrabold tracking-tight sm:text-3xl">{product.name}</h1>
                            <div className="mt-2 flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
                                {product.rating && <span className="flex items-center gap-1.5"><Stars value={product.rating} /> <b className="text-foreground">{product.rating.toFixed(1)}</b> ({product.rating_count})</span>}
                                {product.sold > 0 && <span>{t('product.sold_count', { count: product.sold })}</span>}
                            </div>
                        </div>

                        {variant?.flash_ends_at && (
                            <div className="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-gold px-4 py-2.5 text-gold-foreground">
                                <span className="flex items-center gap-2 font-bold"><Zap className="size-4" /> {t('product.flash_sale')}</span>
                                <span className="flex items-center gap-2 text-sm font-semibold">{t('home.flash_ends')} <CountdownBoxes target={variant.flash_ends_at} /></span>
                            </div>
                        )}

                        <Price price={variant?.price ?? product.price} compare={variant?.compare_price} size="lg" />

                        {product.variants.length > 1 && (
                            <fieldset>
                                <legend className="mb-2 text-sm font-bold">{t('product.choose_variant')}</legend>
                                <div className="flex flex-wrap gap-2">
                                    {product.variants.map((v) => (
                                        <button key={v.id} type="button" aria-pressed={v.id === variant?.id} onClick={() => { setVariantId(v.id); setQty(1); }} className={cn('min-h-11 rounded-lg border px-4 text-sm font-semibold', v.id === variant?.id ? 'border-primary bg-primary-soft text-primary' : 'hover:border-primary/50', v.max_buyable === 0 && 'text-muted-foreground line-through')}>
                                            {v.label}
                                        </button>
                                    ))}
                                </div>
                            </fieldset>
                        )}

                        <div className="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
                            <span className={cn('font-semibold', variant && variant.max_buyable > 0 ? 'text-leaf' : 'text-destructive')}>
                                {!variant || variant.max_buyable === 0 ? t('product.sold_out') : variant.stock_exact !== null ? t('product.stock_left', { count: variant.stock_exact }) : t('product.in_stock')}
                            </span>
                            {variant && <span className="text-muted-foreground">{t('product.weight')}: {grams(variant.weight_gram)}</span>}
                            {already > 0 && <span className="text-muted-foreground">{t('product.in_cart', { count: already })}</span>}
                        </div>

                        {variant && variant.max_buyable > 0 ? (
                            <div className="flex flex-wrap gap-3">
                                <div className="flex h-12 items-center rounded-lg border" role="group" aria-label={t('product.quantity')}>
                                    <button type="button" className="flex size-12 items-center justify-center disabled:opacity-40" onClick={() => setQty(Math.max(1, qty - 1))} disabled={qty <= 1} aria-label={t('common.decrease')}><Minus className="size-4" /></button>
                                    <span className="price w-10 text-center font-bold" aria-live="polite">{qty}</span>
                                    {/* Tombol + berhenti di batas (4.4 titik 1). */}
                                    <button type="button" className="flex size-12 items-center justify-center disabled:opacity-40" onClick={() => setQty(Math.min(maxAddable, qty + 1))} disabled={qty >= maxAddable} aria-label={t('common.increase')}><Plus className="size-4" /></button>
                                </div>
                                <Button size="lg" className="flex-1" onClick={addToCart} disabled={busy || maxAddable < 1}>
                                    <ShoppingBag /> {maxAddable < 1 ? t('product.max_in_cart') : t('product.add_to_cart')}
                                </Button>
                            </div>
                        ) : (
                            <Button size="lg" variant="outline" className="w-full" onClick={toggleWishlist}>
                                <Bell /> {wishlisted ? t('product.notify_on') : t('product.notify_me')}
                            </Button>
                        )}

                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" asChild>
                                <a href={tanyaAdminUrl} target="_blank" rel="noopener noreferrer" className="text-[#128C7E] hover:text-[#128C7E] dark:text-[#25D366]">
                                    <MessageCircle className="size-4 text-[#25D366]" /> Chat Admin
                                </a>
                            </Button>
                            <Button variant="outline" onClick={toggleWishlist} aria-pressed={wishlisted}>
                                <Heart className={cn(wishlisted && 'fill-primary text-primary', heartPop && 'animate-pop')} /> {t('product.wishlist')}
                            </Button>
                            <Button variant="outline" asChild>
                                <a href={`https://wa.me/?text=${encodeURIComponent(shareText)}`} target="_blank" rel="noopener noreferrer"><Share2 /> Bagikan</a>
                            </Button>
                        </div>

                        {product.origin && (
                            <div className="flex gap-3 rounded-xl border bg-card p-4 text-sm">
                                <Truck className="mt-0.5 size-5 shrink-0 text-primary" aria-hidden="true" />
                                <div>
                                    <p className="font-semibold">{t('product.ships_from', { origin: product.origin.name })}</p>
                                    <p className="text-muted-foreground">{t('product.ships_note')}{product.origin.pickup && ` ${t('product.pickup_available')}`}</p>
                                </div>
                            </div>
                        )}

                        {product.producer && (
                            <Link href={localePath(`/produsen/${product.producer.slug}`)} className="flex gap-4 rounded-xl border bg-card p-4 hover:border-primary">
                                <span className="flex size-12 shrink-0 rotate-45 items-center justify-center rounded-xl bg-primary-soft"><Store className="size-5 -rotate-45 text-primary" /></span>
                                <span className="min-w-0">
                                    <span className="block font-bold">{product.producer.name}</span>
                                    <span className="flex items-center gap-1 text-xs text-muted-foreground"><MapPin className="size-3" /> {t('common.village_district', { village: product.producer.village ?? '', district: product.producer.district })}</span>
                                    {product.producer.story && <span className="mt-1 line-clamp-2 block text-sm text-muted-foreground">{product.producer.story}</span>}
                                </span>
                            </Link>
                        )}
                    </div>
                </div>

                <div className="mt-10 grid gap-10 lg:grid-cols-[1.05fr_1fr]">
                    <div className="space-y-8">
                        <section>
                            <h2 className="mb-3 text-lg font-extrabold">{t('product.description')}</h2>
                            <p className="leading-relaxed whitespace-pre-line text-foreground/90">{product.description}</p>
                            {product.tags.length > 0 && (
                                <div className="mt-4 flex flex-wrap gap-2">
                                    {product.tags.map((tag) => <Link key={tag} href={localePath(`/produk?q=${encodeURIComponent(tag)}`)} className="rounded-full bg-muted px-3 py-1 text-xs font-medium">#{tag}</Link>)}
                                </div>
                            )}
                        </section>

                        {product.brewing_instructions && (
                            <section className="rounded-2xl border border-primary/20 bg-primary/5 p-5">
                                <h2 className="mb-3 flex items-center gap-2 text-lg font-extrabold text-primary">
                                    <span>☕</span> Cara Seduh / Penyajian
                                </h2>
                                <p className="leading-relaxed whitespace-pre-line text-foreground/90">{product.brewing_instructions}</p>
                            </section>
                        )}
                    </div>
                    <section>
                        <h2 className="mb-3 text-lg font-extrabold">{t('product.reviews', { count: totalReviews })}</h2>
                        {reviews.length === 0 ? (
                            <p className="text-sm text-muted-foreground">{t('product.no_reviews')}</p>
                        ) : (
                            <ul className="space-y-4">
                                {reviews.map((r) => (
                                    <li key={r.id} className="rounded-xl border bg-card p-4">
                                        <div className="flex items-center justify-between gap-2 text-sm">
                                            <span className="font-semibold">{r.author} <span className="ml-1 rounded bg-leaf-soft px-1.5 py-0.5 text-[10px] font-bold text-leaf">{t('product.verified_buyer')}</span></span>
                                            <Stars value={r.rating} size="size-3.5" />
                                        </div>
                                        {r.body && <p className="mt-2 text-sm">{r.body}</p>}
                                        {r.reply && <p className="mt-3 rounded-lg bg-muted p-3 text-sm"><b>{t('product.store_reply')}:</b> {r.reply}</p>}
                                        <p className="mt-2 text-xs text-muted-foreground">{formatDate(r.date, locale)}</p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                </div>

                {related.length > 0 && (
                    <section className="mt-12">
                        <h2 className="mb-4 text-xl font-extrabold">{t('product.related')}</h2>
                        <ProductGrid products={related} />
                    </section>
                )}

                <Deferred data="forYou" fallback={<div className="mt-12"><ProductGridSkeleton count={5} /></div>}>
                    <ForYou />
                </Deferred>
            </div>
        </>
    );
}

function ForYou() {
    const t = useT();
    const { forYou } = usePage<{ forYou?: ProductCard[] }>().props;

    if (!forYou || forYou.length === 0) {
        return null;
    }

    return (
        <section className="mt-12">
            <h2 className="mb-4 text-xl font-extrabold">{t('home.related')}</h2>
            <ProductGrid products={forYou} />
        </section>
    );
}
