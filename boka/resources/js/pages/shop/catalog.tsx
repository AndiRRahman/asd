import { Link, router } from '@inertiajs/react';
import { SearchX, SlidersHorizontal } from 'lucide-react';
import { useState } from 'react';
import { CategoryIcon, EmptyState } from '@/components/shop/bits';
import { ProductGrid } from '@/components/shop/product-card';
import { Seo } from '@/components/shop/seo';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { useLocalePath, useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { CategoryRef, Paginated, ProductCard } from '@/types/boka';

type Filters = {
    q?: string;
    category?: string;
    producer?: string;
    district?: string;
    rating?: string;
    min_price?: string;
    max_price?: string;
    in_stock?: string | boolean;
    sort?: string;
};

type Props = {
    filters: Filters;
    products: Paginated<ProductCard> & { total: number };
    category: { name: string; slug: string; parent: CategoryRef | null; children: CategoryRef[] } | null;
    facets: { categories: CategoryRef[]; producers: { name: string; slug: string }[]; districts: string[] };
    explore: CategoryRef[];
    hiddenGems?: ProductCard[];
};

const SORTS = ['relevansi', 'terbaru', 'terlaris', 'harga_terendah', 'harga_tertinggi'];

function FilterPanel({ filters, facets, apply }: { filters: Filters; facets: Props['facets']; apply: (patch: Partial<Filters>) => void }) {
    const t = useT();
    const [min, setMin] = useState(filters.min_price ?? '');
    const [max, setMax] = useState(filters.max_price ?? '');
    const select = 'h-11 w-full rounded-lg border border-input bg-card px-3';

    return (
        <div className="space-y-6">
            <fieldset className="space-y-2">
                <legend className="mb-2 text-sm font-bold">{t('catalog.category')}</legend>
                <select className={select} value={filters.category ?? ''} onChange={(e) => apply({ category: e.target.value || undefined })}>
                    <option value="">{t('catalog.all_categories')}</option>
                    {facets.categories.map((c) => <option key={c.slug} value={c.slug}>{c.name}</option>)}
                </select>
            </fieldset>
            <fieldset>
                <legend className="mb-2 text-sm font-bold">{t('catalog.price_range')}</legend>
                <form className="flex items-center gap-2" onSubmit={(e) => { e.preventDefault(); apply({ min_price: min || undefined, max_price: max || undefined }); }}>
                    <input inputMode="numeric" aria-label={t('catalog.min_price')} placeholder={t('catalog.min')} value={min} onChange={(e) => setMin(e.target.value.replace(/\D/g, ''))} className={select} />
                    <span aria-hidden="true">–</span>
                    <input inputMode="numeric" aria-label={t('catalog.max_price')} placeholder={t('catalog.max')} value={max} onChange={(e) => setMax(e.target.value.replace(/\D/g, ''))} className={select} />
                    <Button type="submit" variant="outline" size="sm" className="h-11">OK</Button>
                </form>
            </fieldset>
            <fieldset>
                <legend className="mb-2 text-sm font-bold">{t('catalog.producer')}</legend>
                <select className={select} value={filters.producer ?? ''} onChange={(e) => apply({ producer: e.target.value || undefined })}>
                    <option value="">{t('catalog.all_producers')}</option>
                    {facets.producers.map((p) => <option key={p.slug} value={p.slug}>{p.name}</option>)}
                </select>
            </fieldset>
            <fieldset>
                <legend className="mb-2 text-sm font-bold">{t('catalog.district')}</legend>
                <select className={select} value={filters.district ?? ''} onChange={(e) => apply({ district: e.target.value || undefined })}>
                    <option value="">{t('catalog.all_districts')}</option>
                    {facets.districts.map((d) => <option key={d} value={d}>{d}</option>)}
                </select>
            </fieldset>
            <fieldset>
                <legend className="mb-2 text-sm font-bold">{t('catalog.rating')}</legend>
                <div className="flex gap-2">
                    {['4', '3'].map((r) => (
                        <button key={r} type="button" aria-pressed={filters.rating === r} onClick={() => apply({ rating: filters.rating === r ? undefined : r })} className={cn('h-11 flex-1 rounded-lg border text-sm font-semibold', filters.rating === r && 'border-primary bg-primary-soft text-primary')}>
                            ★ {r}+
                        </button>
                    ))}
                </div>
            </fieldset>
            <label className="flex min-h-11 items-center gap-3 text-sm font-semibold">
                <input type="checkbox" className="size-5 accent-[var(--primary)]" checked={Boolean(filters.in_stock) && filters.in_stock !== '0'} onChange={(e) => apply({ in_stock: e.target.checked ? '1' : undefined })} />
                {t('catalog.in_stock_only')}
            </label>
        </div>
    );
}

export default function Catalog({ filters, products, category, facets, explore, hiddenGems }: Props) {
    const t = useT();
    const localePath = useLocalePath();
    const [filtersOpen, setFiltersOpen] = useState(false);

    const basePath = category ? `/kategori/${category.slug}` : '/produk';

    function apply(patch: Partial<Filters>) {
        const next: Record<string, string> = {};
        for (const [key, value] of Object.entries({ ...filters, ...patch })) {
            if (value !== undefined && value !== null && value !== '' && !(category && key === 'category')) {
                next[key] = String(value);
            }
        }
        const target = patch.category !== undefined && patch.category !== category?.slug ? '/produk' : basePath;
        router.get(localePath(target), next, { preserveScroll: true, preserveState: true });
        setFiltersOpen(false);
    }

    const heading = filters.q ? t('catalog.results_for', { q: filters.q }) : (category?.name ?? t('catalog.all_products'));

    return (
        <>
            <Seo title={heading} description={t('catalog.meta_description')} path={basePath} />
            <div className="mx-auto max-w-7xl px-4 py-6">
                <nav aria-label="Breadcrumb" className="mb-3 text-sm text-muted-foreground">
                    <Link href={localePath('/')} className="hover:text-primary">{t('nav.home')}</Link>
                    {category?.parent && <> / <Link href={localePath(`/kategori/${category.parent.slug}`)} className="hover:text-primary">{category.parent.name}</Link></>}
                    {' / '}<span className="text-foreground">{category?.name ?? t('catalog.all_products')}</span>
                </nav>
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-extrabold tracking-tight sm:text-3xl">{heading}</h1>
                        <p className="mt-1 text-sm text-muted-foreground">{t('catalog.count', { count: products.total })}</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button variant="outline" className="lg:hidden" onClick={() => setFiltersOpen(true)}>
                            <SlidersHorizontal /> {t('catalog.filters')}
                        </Button>
                        <label className="sr-only" htmlFor="sort">{t('catalog.sort')}</label>
                        <select id="sort" value={filters.sort ?? 'relevansi'} onChange={(e) => apply({ sort: e.target.value })} className="h-11 rounded-lg border border-input bg-card px-3 text-sm font-semibold">
                            {SORTS.map((s) => <option key={s} value={s}>{t(`catalog.sort_${s}`)}</option>)}
                        </select>
                    </div>
                </div>

                {category && category.children.length > 0 && (
                    <div className="mt-4 flex gap-2 overflow-x-auto pb-1">
                        {category.children.map((c) => (
                            <Link key={c.slug} href={localePath(`/kategori/${c.slug}`)} className="rounded-full border bg-card px-4 py-2 text-sm font-semibold whitespace-nowrap hover:border-primary">{c.name}</Link>
                        ))}
                    </div>
                )}

                <div className="mt-6 grid gap-8 lg:grid-cols-[240px_1fr]">
                    <aside className="hidden lg:block" aria-label={t('catalog.filters')}>
                        <FilterPanel filters={filters} facets={facets} apply={apply} />
                    </aside>
                    <div>
                        {products.data.length === 0 ? (
                            <EmptyState icon={SearchX} title={t('catalog.empty_title')} body={t('catalog.empty_body')}>
                                {explore.length > 0 && (
                                    <div className="mt-4 w-full">
                                        <p className="mb-3 text-sm font-bold">{t('home.explore')}</p>
                                        <div className="flex flex-wrap justify-center gap-2">
                                            {explore.map((c) => (
                                                <Link key={c.slug} href={localePath(`/kategori/${c.slug}`)} className="flex items-center gap-2 rounded-full border bg-background px-4 py-2.5 text-sm font-semibold hover:border-primary">
                                                    <CategoryIcon name={c.icon} className="size-4 text-primary" /> {c.name}
                                                </Link>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </EmptyState>
                        ) : (
                            <ProductGrid products={products.data} priorityFirst={4} className="lg:grid-cols-3 xl:grid-cols-4" />
                        )}

                        {products.last_page > 1 && (
                            <nav aria-label={t('common.pagination')} className="mt-8 flex justify-center gap-2">
                                {Array.from({ length: products.last_page }, (_, i) => i + 1).map((page) => (
                                    <button key={page} type="button" aria-current={page === products.current_page ? 'page' : undefined} onClick={() => router.get(window.location.pathname, { ...Object.fromEntries(new URLSearchParams(window.location.search)), page }, { preserveScroll: false })} className={cn('size-11 rounded-lg border text-sm font-semibold', page === products.current_page && 'border-primary bg-primary text-primary-foreground')}>
                                        {page}
                                    </button>
                                ))}
                            </nav>
                        )}

                        {hiddenGems && hiddenGems.length > 0 && (
                            <section className="mt-12">
                                <h2 className="mb-1 text-lg font-extrabold">{t('catalog.hidden_gems')}</h2>
                                <p className="mb-4 text-sm text-muted-foreground">{t('home.explore_note')}</p>
                                <ProductGrid products={hiddenGems} className="lg:grid-cols-4 xl:grid-cols-4" />
                            </section>
                        )}
                    </div>
                </div>
            </div>

            <Sheet open={filtersOpen} onOpenChange={setFiltersOpen}>
                <SheetContent side="bottom" className="max-h-[85vh] overflow-y-auto rounded-t-2xl">
                    <SheetHeader><SheetTitle>{t('catalog.filters')}</SheetTitle></SheetHeader>
                    <div className="px-4 pb-6"><FilterPanel filters={filters} facets={facets} apply={apply} /></div>
                </SheetContent>
            </Sheet>
        </>
    );
}
