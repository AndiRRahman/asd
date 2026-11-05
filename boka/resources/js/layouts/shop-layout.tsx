import { Link, router, usePage } from '@inertiajs/react';
import { Home, LayoutGrid, LogOut, MessageCircle, Moon, Package, Search, Settings, ShieldCheck, ShoppingBag, Sun, User, Heart, MapPin } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { CategoryIcon } from '@/components/shop/bits';
import { BokaLogo, MotifDivider, Walasuji } from '@/components/shop/brand';
import { SearchBox } from '@/components/shop/search-box';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { useAppearance } from '@/hooks/use-appearance';
import { useFlashToast } from '@/hooks/use-flash-toast';
import { useLocalePath, useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

function LocaleSwitch() {
    const { locale, locales } = usePage().props;

    return (
        <div className="flex rounded-full border p-0.5 text-xs font-bold" role="group" aria-label="Bahasa / Language">
            {locales.map((code) => (
                <button
                    key={code}
                    type="button"
                    aria-pressed={locale === code}
                    onClick={() => router.post('/preferensi/bahasa', { locale: code, redirect: window.location.pathname + window.location.search })}
                    className={cn('min-w-9 rounded-full px-2 py-1.5 uppercase', locale === code ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground')}
                >
                    {code}
                </button>
            ))}
        </div>
    );
}

function ChatAdminButton() {
    const { store } = usePage<{ store?: { whatsapp?: string } }>().props;
    const rawPhone = store?.whatsapp ?? '0812-0000-0000';
    let phone = rawPhone.replace(/\D/g, '');
    if (phone.startsWith('0')) {
        phone = '62' + phone.slice(1);
    }
    const waUrl = `https://wa.me/${phone}?text=${encodeURIComponent('Halo Admin Tejaseku, saya ingin bertanya tentang produk / pemesanan.')}`;

    return (
        <a
            href={waUrl}
            target="_blank"
            rel="noopener noreferrer"
            className="flex items-center gap-1.5 rounded-full bg-[#25D366]/10 px-3 py-1.5 text-xs font-bold text-[#128C7E] transition-colors hover:bg-[#25D366] hover:text-white dark:bg-[#25D366]/20 dark:text-[#25D366] dark:hover:bg-[#25D366] dark:hover:text-white"
            aria-label="Chat Admin via WhatsApp"
        >
            <MessageCircle className="size-4" />
            <span className="hidden sm:inline">Chat Admin</span>
        </a>
    );
}

function ThemeToggle() {
    const t = useT();
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const { auth } = usePage().props;
    const next = resolvedAppearance === 'dark' ? 'light' : 'dark';

    return (
        <button
            type="button"
            onClick={() => {
                updateAppearance(next);
                if (auth.user) {
                    router.post('/preferensi/tema', { theme: next }, { preserveScroll: true, preserveState: true });
                }
            }}
            className="flex size-11 items-center justify-center rounded-full hover:bg-accent"
            aria-label={next === 'dark' ? t('theme.to_dark') : t('theme.to_light')}
        >
            {resolvedAppearance === 'dark' ? <Sun className="size-5" /> : <Moon className="size-5" />}
        </button>
    );
}

function CartButton() {
    const t = useT();
    const { cartCount } = usePage().props;
    const [bump, setBump] = useState(false);
    const previous = useRef(cartCount);

    // Angka keranjang memantul saat bertambah (12.3).
    useEffect(() => {
        if (cartCount > previous.current) {
            setBump(true);
            const id = window.setTimeout(() => setBump(false), 320);
            previous.current = cartCount;

            return () => window.clearTimeout(id);
        }
        previous.current = cartCount;
    }, [cartCount]);

    return (
        <Link href="/keranjang" id="cart-icon" className="relative flex size-11 items-center justify-center rounded-full hover:bg-accent" aria-label={t('nav.cart_count', { count: cartCount })}>
            <ShoppingBag className="size-5" />
            {cartCount > 0 && (
                <span className={cn('absolute top-1 right-0.5 flex min-w-5 items-center justify-center rounded-full bg-primary px-1 text-[11px] font-bold text-primary-foreground', bump && 'animate-pop')}>
                    {cartCount > 99 ? '99+' : cartCount}
                </span>
            )}
        </Link>
    );
}

function AccountMenu() {
    const t = useT();
    const { auth } = usePage().props;

    if (!auth.user) {
        return (
            <div className="hidden items-center gap-2 md:flex">
                <Link href="/login" className="rounded-full px-4 py-2.5 text-sm font-semibold hover:bg-accent">
                    {t('nav.login')}
                </Link>
                <Link href="/register" className="rounded-full border border-primary px-4 py-2 text-sm font-semibold text-primary hover:bg-primary hover:text-primary-foreground">
                    {t('nav.register')}
                </Link>
            </div>
        );
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger className="hidden size-11 items-center justify-center rounded-full bg-primary-soft font-bold text-primary md:flex" aria-label={t('nav.account')}>
                {auth.user.name.charAt(0).toUpperCase()}
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
                <DropdownMenuLabel className="truncate">{auth.user.name}</DropdownMenuLabel>
                <DropdownMenuSeparator />
                {auth.isStaff && (
                    <DropdownMenuItem asChild>
                        <Link href="/panel"><ShieldCheck className="size-4" /> {t('nav.panel')}</Link>
                    </DropdownMenuItem>
                )}
                <DropdownMenuItem asChild><Link href="/pesanan"><Package className="size-4" /> {t('nav.orders')}</Link></DropdownMenuItem>
                <DropdownMenuItem asChild><Link href="/wishlist"><Heart className="size-4" /> {t('nav.wishlist')}</Link></DropdownMenuItem>
                <DropdownMenuItem asChild><Link href="/akun/alamat"><MapPin className="size-4" /> {t('nav.addresses')}</Link></DropdownMenuItem>
                <DropdownMenuItem asChild><Link href="/settings/profile"><Settings className="size-4" /> {t('nav.settings')}</Link></DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                    <Link href="/logout" method="post" as="button" className="w-full"><LogOut className="size-4" /> {t('nav.logout')}</Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function SiteHeader({ onCategories }: { onCategories: () => void }) {
    const t = useT();
    const localePath = useLocalePath();
    const { navCategories } = usePage().props;

    return (
        // Header dan kotak pencarian menempel di atas saat menggulir (12.2).
        <header className="sticky top-0 z-40 border-b bg-background/90 backdrop-blur supports-[backdrop-filter]:bg-background/75">
            <div className="mx-auto flex h-16 max-w-7xl items-center gap-3 px-4 lg:gap-6">
                <Link href={localePath('/')} aria-label={t('nav.home')} className="shrink-0">
                    <BokaLogo />
                </Link>
                <button type="button" onClick={onCategories} className="hidden items-center gap-2 rounded-full px-3 py-2 text-sm font-semibold hover:bg-accent lg:flex">
                    <LayoutGrid className="size-4" /> {t('nav.categories')}
                </button>
                <SearchBox className="hidden flex-1 md:block" />
                <div className="ml-auto flex items-center gap-1 md:ml-0">
                    <ChatAdminButton />
                    <ThemeToggle />
                    <CartButton />
                    <AccountMenu />
                </div>
            </div>
            <div className="px-4 pb-3 md:hidden">
                <SearchBox />
            </div>
            <nav aria-label={t('nav.categories')} className="hidden border-t lg:block">
                <ul className="mx-auto flex max-w-7xl gap-1 overflow-x-auto px-4 py-1.5 text-sm">
                    {navCategories.map((c) => (
                        <li key={c.slug}>
                            <Link href={localePath(`/kategori/${c.slug}`)} className="flex items-center gap-2 rounded-full px-3 py-1.5 whitespace-nowrap text-muted-foreground hover:bg-accent hover:text-foreground">
                                <CategoryIcon name={c.icon} className="size-4" /> {c.name}
                            </Link>
                        </li>
                    ))}
                </ul>
            </nav>
        </header>
    );
}

/** Navigasi bawah 5 tab di HP: Beranda, Kategori, Cari, Keranjang, Akun (12.2). */
function BottomNav({ onCategories }: { onCategories: () => void }) {
    const t = useT();
    const localePath = useLocalePath();
    const { cartCount, auth } = usePage().props;
    const path = typeof window !== 'undefined' ? window.location.pathname : '';
    const tab = 'flex flex-1 flex-col items-center justify-center gap-0.5 py-2 text-[11px] font-semibold min-h-14';

    return (
        <nav aria-label={t('nav.main')} className="fixed inset-x-0 bottom-0 z-40 flex border-t bg-background/95 pb-[env(safe-area-inset-bottom)] backdrop-blur md:hidden">
            <Link href={localePath('/')} className={cn(tab, (path === '/' || path === '/en') ? 'text-primary' : 'text-muted-foreground')}>
                <Home className="size-5" /> {t('nav.home')}
            </Link>
            <button type="button" onClick={onCategories} className={cn(tab, 'text-muted-foreground')}>
                <LayoutGrid className="size-5" /> {t('nav.categories')}
            </button>
            <button type="button" onClick={() => document.getElementById('site-search')?.focus()} className={cn(tab, 'text-muted-foreground')}>
                <Search className="size-5" /> {t('nav.search')}
            </button>
            <Link href="/keranjang" className={cn(tab, 'relative', path === '/keranjang' ? 'text-primary' : 'text-muted-foreground')}>
                <ShoppingBag className="size-5" /> {t('nav.cart')}
                {cartCount > 0 && <span className="absolute top-1.5 left-1/2 ml-1.5 rounded-full bg-primary px-1.5 text-[10px] text-primary-foreground">{cartCount}</span>}
            </Link>
            <Link href={auth.user ? '/pesanan' : '/login'} className={cn(tab, path.startsWith('/pesanan') ? 'text-primary' : 'text-muted-foreground')}>
                <User className="size-5" /> {t('nav.account')}
            </Link>
        </nav>
    );
}

function SiteFooter() {
    const t = useT();
    const localePath = useLocalePath();
    const { store } = usePage().props;

    return (
        <footer className="relative mt-16 overflow-hidden border-t bg-card pb-24 md:pb-0">
            <Walasuji className="text-primary opacity-[0.04]" id="walasuji-footer" />
            <div className="relative mx-auto grid max-w-7xl gap-10 px-4 py-12 sm:grid-cols-2 lg:grid-cols-4">
                <div className="space-y-3">
                    <BokaLogo />
                    <p className="text-sm text-muted-foreground">{t('footer.about')}</p>
                </div>
                <div>
                    <h2 className="mb-3 text-sm font-bold">{t('footer.contact')}</h2>
                    <ul className="space-y-1.5 text-sm text-muted-foreground">
                        <li>WhatsApp {store.whatsapp}</li>
                        <li>{store.email}</li>
                        <li>{store.address}</li>
                    </ul>
                </div>
                <div>
                    <h2 className="mb-3 text-sm font-bold">{t('footer.help')}</h2>
                    <ul className="space-y-1.5 text-sm">
                        <li><Link className="text-muted-foreground hover:text-primary" href={localePath('/halaman/kebijakan-privasi')}>{t('footer.privacy')}</Link></li>
                        <li><Link className="text-muted-foreground hover:text-primary" href={localePath('/halaman/syarat-ketentuan')}>{t('footer.terms')}</Link></li>
                        <li><Link className="text-muted-foreground hover:text-primary" href={localePath('/halaman/kebijakan-pengembalian')}>{t('footer.returns')}</Link></li>
                        <li><Link className="text-muted-foreground hover:text-primary" href={localePath('/halaman/tentang')}>{t('footer.about_link')}</Link></li>
                    </ul>
                </div>
                <div className="space-y-3">
                    <h2 className="text-sm font-bold">Pemesanan Langsung</h2>
                    <p className="rounded-xl border border-primary/20 bg-primary/5 p-3 text-sm text-muted-foreground">Pemesanan dilakukan langsung via WhatsApp atau tunai/COD saat barang diterima.</p>
                </div>
            </div>
            <MotifDivider className="mx-auto max-w-7xl px-4" />
            <p className="relative py-6 text-center text-xs text-muted-foreground">© {new Date().getFullYear()} Tejaseku. {t('footer.rights')}</p>
        </footer>
    );
}

function CategorySheet({ open, onOpenChange }: { open: boolean; onOpenChange: (open: boolean) => void }) {
    const t = useT();
    const localePath = useLocalePath();
    const { navCategories } = usePage().props;

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent side="left" className="w-80">
                <SheetHeader>
                    <SheetTitle>{t('nav.categories')}</SheetTitle>
                </SheetHeader>
                <ul className="space-y-1 px-4">
                    {navCategories.map((c) => (
                        <li key={c.slug}>
                            <Link href={localePath(`/kategori/${c.slug}`)} onClick={() => onOpenChange(false)} className="flex items-center gap-3 rounded-lg px-3 py-3 font-medium hover:bg-accent">
                                <span className="flex size-9 items-center justify-center rounded-lg bg-primary-soft text-primary"><CategoryIcon name={c.icon} className="size-5" /></span>
                                {c.name}
                            </Link>
                        </li>
                    ))}
                </ul>
            </SheetContent>
        </Sheet>
    );
}

function FloatingChatButton() {
    const { store } = usePage<{ store?: { whatsapp?: string } }>().props;
    const rawPhone = store?.whatsapp ?? '0812-0000-0000';
    let phone = rawPhone.replace(/\D/g, '');
    if (phone.startsWith('0')) {
        phone = '62' + phone.slice(1);
    }
    const waUrl = `https://wa.me/${phone}?text=${encodeURIComponent('Halo Admin Tejaseku, saya ingin bertanya tentang produk / pemesanan.')}`;

    return (
        <a
            href={waUrl}
            target="_blank"
            rel="noopener noreferrer"
            className="fixed right-5 bottom-20 z-40 flex items-center gap-2 rounded-full bg-[#25D366] px-4 py-3 text-sm font-bold text-white shadow-lg transition-transform hover:scale-105 hover:bg-[#20ba5a] sm:bottom-6"
            aria-label="Hubungi Admin via WhatsApp"
        >
            <MessageCircle className="size-5" />
            <span>Chat Admin</span>
        </a>
    );
}

export default function ShopLayout({ children }: { children: React.ReactNode }) {
    const t = useT();
    const [categoriesOpen, setCategoriesOpen] = useState(false);
    useFlashToast();

    return (
        <div className="flex min-h-screen flex-col">
            <a href="#main" className="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:rounded focus:bg-primary focus:px-4 focus:py-2 focus:text-primary-foreground">
                {t('nav.skip')}
            </a>
            <SiteHeader onCategories={() => setCategoriesOpen(true)} />
            <main id="main" className="flex-1 animate-rise">
                {children}
            </main>
            <SiteFooter />
            <FloatingChatButton />
            <BottomNav onCategories={() => setCategoriesOpen(true)} />
            <CategorySheet open={categoriesOpen} onOpenChange={setCategoriesOpen} />
        </div>
    );
}
