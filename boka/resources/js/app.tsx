import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';
import ShopLayout from '@/layouts/shop-layout';

const appName = import.meta.env.VITE_APP_NAME || 'e-TEJASEKU';

void createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : `${appName}`),
    layout: (name) => {
        switch (true) {
            case name.startsWith('auth/'):
                return AuthLayout;
            // Layout panel dipasang oleh PanelPage agar kode panel tidak ikut dimuat di halaman toko.
            case name.startsWith('panel/'):
                return null;
            case name.startsWith('settings/'):
                return [ShopLayout, SettingsLayout];
            default:
                return ShopLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#D9A21B',
    },
});

initializeTheme();
