import { router } from '@inertiajs/react';
import { Clock, Search, Sparkles, TrendingUp, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { rupiah } from '@/lib/format';
import { jsonRequest } from '@/lib/http';
import { useLocalePath, useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { CategoryIcon } from './bits';

type Suggest =
    | { mode: 'empty'; history: string[]; popular: string[] }
    | { mode: 'letter'; categories: { name: string; slug: string }[]; keywords: string[] }
    | {
          mode: 'results' | 'empty_results';
          products: { id: number; name: string; slug: string; price: number; image: string | null; producer: string | null }[];
          categories: { name: string; slug: string }[];
          producers: { name: string; slug: string; type: string }[];
          keywords: string[];
          did_you_mean?: string | null;
          explore?: { name: string; slug: string }[];
      };

type Item = { key: string; href: string; log?: { q: string; product_id?: number } };

/** Menebalkan bagian yang cocok dengan ketikan. */
function Highlight({ text, query }: { text: string; query: string }) {
    const index = text.toLowerCase().indexOf(query.toLowerCase());

    if (!query || index < 0) {
        return <>{text}</>;
    }

    return (
        <>
            {text.slice(0, index)}
            <strong className="font-bold text-primary">{text.slice(index, index + query.length)}</strong>
            {text.slice(index + query.length)}
        </>
    );
}

export function SearchBox({ className, autoFocus = false, onNavigate }: { className?: string; autoFocus?: boolean; onNavigate?: () => void }) {
    const t = useT();
    const localePath = useLocalePath();
    const [query, setQuery] = useState(() => (typeof window !== 'undefined' ? (new URLSearchParams(window.location.search).get('q') ?? '') : ''));
    const [data, setData] = useState<Suggest | null>(null);
    const [open, setOpen] = useState(false);
    const [active, setActive] = useState(-1);
    const controller = useRef<AbortController | null>(null);
    const idleLog = useRef<number | undefined>(undefined);
    const wrapper = useRef<HTMLDivElement>(null);

    // Permintaan dikirim 150 ms setelah ketikan terakhir; permintaan lama dibatalkan (7.1).
    useEffect(() => {
        if (!open) {
            return;
        }

        const timer = window.setTimeout(() => {
            controller.current?.abort();
            controller.current = new AbortController();
            jsonRequest<Suggest>(`/cari/saran?q=${encodeURIComponent(query)}`, { signal: controller.current.signal })
                .then((result) => {
                    setData(result);
                    setActive(-1);

                    // Berhenti mengetik 1 detik dengan hasil tampil juga dihitung satu pencarian (7.2).
                    window.clearTimeout(idleLog.current);
                    if (result.mode === 'results' && query.trim().length >= 2) {
                        idleLog.current = window.setTimeout(() => log(query, result.products.length), 1000);
                    }
                })
                .catch(() => undefined);
        }, 150);

        return () => window.clearTimeout(timer);
    }, [query, open]);

    useEffect(() => {
        const close = (event: MouseEvent) => {
            if (!wrapper.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        };
        document.addEventListener('mousedown', close);

        return () => document.removeEventListener('mousedown', close);
    }, []);

    const loggedQueries = useRef(new Set<string>());

    function log(q: string, results: number, productId?: number) {
        const key = `${q}|${productId ?? ''}`;
        if (loggedQueries.current.has(key)) {
            return;
        }
        loggedQueries.current.add(key);
        void jsonRequest('/cari/log', { method: 'POST', body: { q, results, product_id: productId ?? null } }).catch(() => undefined);
    }

    const items: Item[] = useMemo(() => {
        if (!data) {
            return [];
        }

        if (data.mode === 'empty') {
            return [...data.history, ...data.popular].map((k) => ({ key: `k:${k}`, href: localePath(`/produk?q=${encodeURIComponent(k)}`), log: { q: k } }));
        }

        if (data.mode === 'letter') {
            return [
                ...data.categories.map((c) => ({ key: `c:${c.slug}`, href: localePath(`/kategori/${c.slug}`) })),
                ...data.keywords.map((k) => ({ key: `k:${k}`, href: localePath(`/produk?q=${encodeURIComponent(k)}`), log: { q: k } })),
            ];
        }

        return [
            ...data.products.map((p) => ({ key: `p:${p.slug}`, href: localePath(`/produk/${p.slug}`), log: { q: query, product_id: p.id } })),
            ...data.categories.map((c) => ({ key: `c:${c.slug}`, href: localePath(`/kategori/${c.slug}`) })),
            ...data.producers.map((p) => ({ key: `r:${p.slug}`, href: localePath(`/produsen/${p.slug}`) })),
            ...data.keywords.map((k) => ({ key: `k:${k}`, href: localePath(`/produk?q=${encodeURIComponent(k)}`), log: { q: k } })),
            { key: 'all', href: localePath(`/produk?q=${encodeURIComponent(query)}`), log: { q: query } },
        ];
    }, [data, query, localePath]);

    // Memilih saran dihitung satu pencarian; produk yang diklik ikut dicatat untuk rekomendasi (7.2).
    function go(href: string, logEntry?: { q: string; product_id?: number }) {
        if (logEntry) {
            const results = data && 'products' in data ? data.products.length : 1;
            log(logEntry.q, results, logEntry.product_id);
        }
        setOpen(false);
        onNavigate?.();
        router.visit(href);
    }

    function submit(event: React.FormEvent) {
        event.preventDefault();

        if (active >= 0 && items[active]) {
            go(items[active].href, items[active].log);

            return;
        }

        if (query.trim()) {
            go(localePath(`/produk?q=${encodeURIComponent(query.trim())}`), { q: query.trim() });
        }
    }

    function onKeyDown(event: React.KeyboardEvent) {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setOpen(true);
            setActive((i) => Math.min(items.length - 1, i + 1));
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActive((i) => Math.max(-1, i - 1));
        } else if (event.key === 'Escape') {
            setOpen(false);
        }
    }

    const optionClass = (key: string) =>
        cn(
            'flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm hover:bg-accent',
            items[active]?.key === key && 'bg-accent',
        );

    return (
        <div ref={wrapper} className={cn('relative', className)}>
            <form onSubmit={submit} role="search">
                <label htmlFor="site-search" className="sr-only">
                    {t('search.label')}
                </label>
                <div className="flex h-11 items-center gap-2 rounded-full border border-input bg-card pr-1.5 pl-4 focus-within:ring-2 focus-within:ring-ring/30">
                    <Search className="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                    <input
                        id="site-search"
                        type="search"
                        autoComplete="off"
                        autoFocus={autoFocus}
                        value={query}
                        placeholder={t('search.placeholder')}
                        onChange={(e) => {
                            setQuery(e.target.value);
                            setOpen(true);
                        }}
                        onFocus={() => setOpen(true)}
                        onKeyDown={onKeyDown}
                        role="combobox"
                        aria-expanded={open}
                        aria-controls="search-suggestions"
                        className="h-full min-w-0 flex-1 bg-transparent outline-none placeholder:text-muted-foreground"
                    />
                    {query && (
                        <button type="button" onClick={() => setQuery('')} className="flex size-8 items-center justify-center rounded-full text-muted-foreground hover:bg-muted" aria-label={t('search.clear')}>
                            <X className="size-4" />
                        </button>
                    )}
                    <button type="submit" className="hidden h-8 rounded-full bg-primary px-4 text-sm font-semibold text-primary-foreground sm:block">
                        {t('search.submit')}
                    </button>
                </div>
            </form>

            {open && data && (
                <div id="search-suggestions" role="listbox" className="absolute inset-x-0 top-12 z-50 max-h-[70vh] animate-rise overflow-y-auto rounded-2xl border bg-popover p-2 shadow-xl">
                    {data.mode === 'empty' && (
                        <>
                            {data.history.length > 0 && <p className="px-3 pt-2 pb-1 text-xs font-semibold text-muted-foreground uppercase">{t('search.history')}</p>}
                            {data.history.map((k) => (
                                <button key={`h${k}`} type="button" className={optionClass(`k:${k}`)} onClick={() => go(localePath(`/produk?q=${encodeURIComponent(k)}`), { q: k })}>
                                    <Clock className="size-4 text-muted-foreground" /> {k}
                                </button>
                            ))}
                            <p className="px-3 pt-2 pb-1 text-xs font-semibold text-muted-foreground uppercase">{t('search.popular_week')}</p>
                            {data.popular.length === 0 && <p className="px-3 py-2 text-sm text-muted-foreground">{t('search.no_popular')}</p>}
                            {data.popular.map((k) => (
                                <button key={`p${k}`} type="button" className={optionClass(`k:${k}`)} onClick={() => go(localePath(`/produk?q=${encodeURIComponent(k)}`), { q: k })}>
                                    <TrendingUp className="size-4 text-primary" /> {k}
                                </button>
                            ))}
                        </>
                    )}

                    {data.mode === 'letter' && (
                        <>
                            {data.categories.map((c) => (
                                <button key={c.slug} type="button" className={optionClass(`c:${c.slug}`)} onClick={() => go(localePath(`/kategori/${c.slug}`))}>
                                    <CategoryIcon className="size-4 text-primary" /> {c.name}
                                </button>
                            ))}
                            {data.keywords.map((k) => (
                                <button key={k} type="button" className={optionClass(`k:${k}`)} onClick={() => go(localePath(`/produk?q=${encodeURIComponent(k)}`), { q: k })}>
                                    <Search className="size-4 text-muted-foreground" /> <Highlight text={k} query={query} />
                                </button>
                            ))}
                        </>
                    )}

                    {(data.mode === 'results' || data.mode === 'empty_results') && (
                        <>
                            {data.products.map((p) => (
                                <button key={p.slug} type="button" className={optionClass(`p:${p.slug}`)} onClick={() => go(localePath(`/produk/${p.slug}`), { q: query, product_id: p.id })}>
                                    {p.image ? <img src={p.image} alt="" width={40} height={40} className="size-10 rounded-md bg-muted object-cover" /> : <span className="size-10 rounded-md bg-muted" />}
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate font-medium">
                                            <Highlight text={p.name} query={query} />
                                        </span>
                                        <span className="block truncate text-xs text-muted-foreground">{p.producer}</span>
                                    </span>
                                    <span className="price text-sm font-semibold">{rupiah(p.price)}</span>
                                </button>
                            ))}
                            {data.categories.length > 0 && <p className="px-3 pt-2 pb-1 text-xs font-semibold text-muted-foreground uppercase">{t('search.categories')}</p>}
                            {data.categories.map((c) => (
                                <button key={c.slug} type="button" className={optionClass(`c:${c.slug}`)} onClick={() => go(localePath(`/kategori/${c.slug}`))}>
                                    <CategoryIcon className="size-4 text-primary" /> <Highlight text={c.name} query={query} />
                                </button>
                            ))}
                            {data.producers.length > 0 && <p className="px-3 pt-2 pb-1 text-xs font-semibold text-muted-foreground uppercase">{t('search.producers')}</p>}
                            {data.producers.map((p) => (
                                <button key={p.slug} type="button" className={optionClass(`r:${p.slug}`)} onClick={() => go(localePath(`/produsen/${p.slug}`))}>
                                    <Sparkles className="size-4 text-gold" /> <Highlight text={p.name} query={query} />
                                    <span className="ml-auto text-xs text-muted-foreground">{p.type}</span>
                                </button>
                            ))}
                            {data.keywords.map((k) => (
                                <button key={k} type="button" className={optionClass(`k:${k}`)} onClick={() => go(localePath(`/produk?q=${encodeURIComponent(k)}`), { q: k })}>
                                    <Search className="size-4 text-muted-foreground" /> <Highlight text={k} query={query} />
                                </button>
                            ))}

                            {data.mode === 'empty_results' && (
                                <div className="px-3 py-3 text-sm">
                                    <p className="text-muted-foreground">{t('search.no_results', { q: query })}</p>
                                    {data.did_you_mean && (
                                        <p className="mt-1">
                                            {t('search.did_you_mean')}{' '}
                                            <button type="button" className="font-semibold text-primary underline" onClick={() => setQuery(data.did_you_mean ?? '')}>
                                                {data.did_you_mean}
                                            </button>
                                        </p>
                                    )}
                                    {data.explore && data.explore.length > 0 && (
                                        <div className="mt-3">
                                            <p className="mb-2 text-xs font-semibold text-muted-foreground uppercase">{t('home.explore')}</p>
                                            <div className="flex flex-wrap gap-2">
                                                {data.explore.map((c) => (
                                                    <button key={c.slug} type="button" onClick={() => go(localePath(`/kategori/${c.slug}`))} className="rounded-full border px-3 py-1.5 text-xs font-medium hover:border-primary">
                                                        {c.name}
                                                    </button>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            )}

                            {data.mode === 'results' && (
                                <button type="button" className={cn(optionClass('all'), 'mt-1 border-t font-semibold text-primary')} onClick={() => go(localePath(`/produk?q=${encodeURIComponent(query)}`), { q: query })}>
                                    <Search className="size-4" /> {t('search.all_for', { q: query })}
                                </button>
                            )}
                        </>
                    )}
                </div>
            )}
        </div>
    );
}
