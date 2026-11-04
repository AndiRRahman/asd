import type { Auth } from '@/types/auth';
import type { CategoryRef } from '@/types/boka';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            store: {
                name: string;
                long_name: string;
                tagline: string;
                email: string;
                whatsapp: string;
                address: string;
            };
            auth: Auth;
            locale: 'id' | 'en';
            locales: string[];
            translations: Record<string, string>;
            cartCount: number;
            navCategories: CategoryRef[];
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
