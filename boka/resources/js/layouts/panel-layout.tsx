import { Link, usePage } from '@inertiajs/react';
import {
    Boxes, ClipboardList, CreditCard, FileClock, Image, LayoutDashboard, LogOut, MapPinned,
    Package, Settings, ShieldCheck, Store, Tags, Truck, Users, Wrench, BarChart3,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { BokaLogo } from '@/components/shop/brand';
import {
    Sidebar, SidebarContent, SidebarFooter, SidebarGroup, SidebarGroupLabel, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem, SidebarTrigger,
} from '@/components/ui/sidebar';
import { useFlashToast } from '@/hooks/use-flash-toast';

type Item = { title: string; href: string; icon: LucideIcon; can?: string };

/** Menu panel. Setiap item hanya tampil jika pengguna punya izinnya (bagian 2). */
const GROUPS: { label: string; items: Item[] }[] = [
    {
        label: 'Kerja harian',
        items: [
            { title: 'Antrean kerja', href: '/panel', icon: LayoutDashboard },
            { title: 'Pesanan', href: '/panel/pesanan', icon: ClipboardList, can: 'pesanan.kelola' },
        ],
    },
    {
        label: 'Barang',
        items: [
            { title: 'Produk', href: '/panel/produk', icon: Package, can: 'produk.kelola' },
            { title: 'Stok & opname', href: '/panel/stok', icon: Boxes, can: 'stok.kelola' },
            { title: 'Kategori', href: '/panel/kelola/category', icon: Tags, can: 'produk.kelola' },
            { title: 'Produsen', href: '/panel/kelola/producer', icon: Store, can: 'produk.kelola' },
            { title: 'Titik asal kirim', href: '/panel/kelola/origin', icon: MapPinned, can: 'produk.kelola' },
        ],
    },
    {
        label: 'Promosi',
        items: [
            { title: 'Banner', href: '/panel/kelola/banner', icon: Image, can: 'promosi.kelola' },
        ],
    },
    {
        label: 'Laporan',
        items: [
            { title: 'Laporan', href: '/panel/laporan', icon: BarChart3, can: 'laporan.lihat' },
        ],
    },
    {
        label: 'Super Admin',
        items: [
            { title: 'Pengguna panel', href: '/panel/pengguna', icon: Users, can: 'admin.kelola' },
            { title: 'Pengaturan toko', href: '/panel/pengaturan', icon: Settings, can: 'pengaturan.kelola' },
            { title: 'Log audit', href: '/panel/audit', icon: FileClock },
            { title: 'Sistem', href: '/panel/sistem', icon: Wrench, can: 'pengaturan.kelola' },
        ],
    },
];

export default function PanelLayout({ children }: { children: React.ReactNode }) {
    const { auth } = usePage().props;
    const url = usePage().url;
    useFlashToast();

    const allowed = (item: Item) => !item.can || auth.isSuperAdmin || auth.permissions.includes(item.can);
    const isActive = (href: string) => (href === '/panel' ? url === '/panel' : url.startsWith(href));

    return (
        <AppShell variant="sidebar">
            <Sidebar collapsible="icon" variant="inset">
                <SidebarHeader>
                    <Link href="/panel" className="flex items-center gap-2 px-2 py-1.5">
                        <BokaLogo compact />
                        <span className="rounded bg-primary px-1.5 py-0.5 text-[10px] font-bold text-primary-foreground uppercase group-data-[collapsible=icon]:hidden">Panel</span>
                    </Link>
                </SidebarHeader>
                <SidebarContent>
                    {GROUPS.map((group) => {
                        const items = group.items.filter(allowed);

                        return items.length === 0 ? null : (
                            <SidebarGroup key={group.label}>
                                <SidebarGroupLabel>{group.label}</SidebarGroupLabel>
                                <SidebarMenu>
                                    {items.map((item) => (
                                        <SidebarMenuItem key={item.href}>
                                            <SidebarMenuButton asChild isActive={isActive(item.href)} tooltip={item.title} className="h-10">
                                                <Link href={item.href}><item.icon /> <span>{item.title}</span></Link>
                                            </SidebarMenuButton>
                                        </SidebarMenuItem>
                                    ))}
                                </SidebarMenu>
                            </SidebarGroup>
                        );
                    })}
                </SidebarContent>
                <SidebarFooter>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild tooltip="Lihat toko"><Link href="/"><Truck /> <span>Lihat toko</span></Link></SidebarMenuButton>
                        </SidebarMenuItem>
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild tooltip="Keamanan akun"><Link href="/settings/security"><ShieldCheck /> <span>Keamanan akun</span></Link></SidebarMenuButton>
                        </SidebarMenuItem>
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild tooltip="Keluar"><Link href="/logout" method="post" as="button"><LogOut /> <span>Keluar ({auth.user?.name})</span></Link></SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarFooter>
            </Sidebar>
            <AppContent variant="sidebar" className="min-w-0 overflow-x-clip">
                <header className="flex h-14 items-center gap-2 border-b px-4">
                    <SidebarTrigger className="size-10" />
                    <span className="text-sm text-muted-foreground">Panel Admin Karang Taruna (e-TEJASEKU)</span>
                </header>
                <div className="flex-1 p-4 md:p-6">{children}</div>
            </AppContent>
        </AppShell>
    );
}
