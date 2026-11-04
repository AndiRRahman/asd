import { Link, router, usePage } from '@inertiajs/react';
import { AlertTriangle, Info, Minus, Package, Plus, ShoppingBag, Trash2 } from 'lucide-react';
import { EmptyState, Price, ProductImage } from '@/components/shop/bits';
import { ProductGrid } from '@/components/shop/product-card';
import { Seo } from '@/components/shop/seo';
import { Button } from '@/components/ui/button';
import { rupiah } from '@/lib/format';
import { useLocalePath, useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { CartLine, ProductCard } from '@/types/boka';

type Props = { lines: CartLine[]; subtotal: number; hasIssues: boolean; packageCount: number; recommendations?: ProductCard[] };

export default function Cart({ lines, subtotal, hasIssues, packageCount, recommendations }: Props) {
    const t = useT();
    const localePath = useLocalePath();
    const { auth } = usePage().props;

    const update = (line: CartLine, qty: number) => router.patch(`/keranjang/${line.variant_id}`, { qty }, { preserveScroll: true });
    const remove = (line: CartLine) => router.delete(`/keranjang/${line.variant_id}`, { preserveScroll: true });

    if (lines.length === 0) {
        return (
            <div className="mx-auto max-w-3xl px-4 py-10">
                <Seo title={t('cart.title')} />
                <EmptyState icon={ShoppingBag} title={t('cart.empty_title')} body={t('cart.empty_body')}>
                    <Button asChild className="mt-2"><Link href={localePath('/produk')}>{t('cart.browse')}</Link></Button>
                </EmptyState>
                {recommendations && recommendations.length > 0 && (
                    <section className="mt-10">
                        <h2 className="mb-4 text-lg font-extrabold">{t('home.related')}</h2>
                        <ProductGrid products={recommendations} className="lg:grid-cols-4 xl:grid-cols-4" />
                    </section>
                )}
            </div>
        );
    }

    const changed = lines.filter((l) => l.price_changed);

    return (
        <>
            <Seo title={t('cart.title')} />
            <div className="mx-auto max-w-7xl px-4 py-6">
                <h1 className="text-2xl font-extrabold tracking-tight sm:text-3xl">{t('cart.title')}</h1>

                <div className="mt-6 grid gap-8 lg:grid-cols-[1fr_360px]">
                    <div className="space-y-4">
                        {hasIssues && (
                            <div role="alert" className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-destructive/40 bg-destructive/5 p-4 text-sm">
                                <span className="flex items-center gap-2 font-semibold text-destructive"><AlertTriangle className="size-5" /> {t('cart.issues')}</span>
                                <Button variant="outline" size="sm" onClick={() => router.post('/keranjang/sesuaikan', {}, { preserveScroll: true })}>{t('cart.adjust')}</Button>
                            </div>
                        )}
                        {changed.length > 0 && (
                            <div className="flex items-start gap-2 rounded-xl border border-gold/50 bg-gold-soft p-4 text-sm">
                                <Info className="mt-0.5 size-4 shrink-0" /> {t('cart.price_changed', { count: changed.length })}
                            </div>
                        )}
                        {packageCount > 1 && (
                            <div className="flex items-start gap-2 rounded-xl border bg-card p-4 text-sm">
                                <Package className="mt-0.5 size-4 shrink-0 text-primary" /> {t('cart.multi_package', { count: packageCount })}
                            </div>
                        )}

                        <ul className="divide-y rounded-2xl border bg-card">
                            {lines.map((line) => (
                                <li key={line.variant_id} className={cn('flex gap-4 p-4', line.issue && 'bg-destructive/5')}>
                                    <Link href={localePath(`/produk/${line.product.slug}`)} className="w-20 shrink-0 overflow-hidden rounded-lg border sm:w-24">
                                        <ProductImage image={line.product.image} alt={line.product.name} />
                                    </Link>
                                    <div className="min-w-0 flex-1 space-y-1">
                                        <Link href={localePath(`/produk/${line.product.slug}`)} className="line-clamp-2 font-semibold hover:text-primary">{line.product.name}</Link>
                                        <p className="text-xs text-muted-foreground">{[line.variant_label, line.product.producer].filter(Boolean).join(' · ')}</p>
                                        <Price price={line.unit_price} compare={line.compare_price} size="sm" />
                                        {line.issue && (
                                            <p className="text-sm font-bold text-destructive">{line.issue === 'habis' ? t('cart.issue_soldout') : t('cart.issue_left', { count: line.available })}</p>
                                        )}
                                        <div className="flex items-center justify-between gap-3 pt-2">
                                            <div className="flex h-11 items-center rounded-lg border" role="group" aria-label={t('product.quantity')}>
                                                <button type="button" className="flex size-11 items-center justify-center disabled:opacity-40" disabled={line.qty <= 1} onClick={() => update(line, line.qty - 1)} aria-label={t('common.decrease')}><Minus className="size-4" /></button>
                                                <span className="price w-8 text-center font-bold">{line.qty}</span>
                                                <button type="button" className="flex size-11 items-center justify-center disabled:opacity-40" disabled={line.qty >= line.available} onClick={() => update(line, line.qty + 1)} aria-label={t('common.increase')}><Plus className="size-4" /></button>
                                            </div>
                                            <span className="price font-bold">{rupiah(line.line_total)}</span>
                                            <button type="button" onClick={() => remove(line)} className="flex size-11 items-center justify-center rounded-lg text-muted-foreground hover:bg-muted hover:text-destructive" aria-label={t('cart.remove')}><Trash2 className="size-4" /></button>
                                        </div>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </div>

                    <aside className="lg:sticky lg:top-24 lg:self-start">
                        <div className="space-y-4 rounded-2xl border bg-card p-5">
                            <h2 className="font-extrabold">{t('cart.summary')}</h2>
                            <div className="flex justify-between text-sm"><span>{t('cart.subtotal', { count: lines.reduce((s, l) => s + l.qty, 0) })}</span><span className="price font-bold">{rupiah(subtotal)}</span></div>
                            <p className="text-xs text-muted-foreground">{t('cart.shipping_note')}</p>
                            {hasIssues ? (
                                <Button size="lg" className="w-full" disabled>{t('cart.fix_first')}</Button>
                            ) : (
                                <Button size="lg" className="w-full" asChild>
                                    <Link href={auth.user ? '/checkout' : '/login'}>{auth.user ? t('cart.checkout') : t('cart.login_to_checkout')}</Link>
                                </Button>
                            )}
                        </div>
                    </aside>
                </div>
            </div>
        </>
    );
}
