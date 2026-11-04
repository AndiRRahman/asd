import { Head, usePage } from '@inertiajs/react';

/**
 * Meta Open Graph dirender di server (SSR), sehingga tautan WhatsApp memunculkan foto, nama, dan harga (3.3, K13).
 * hreflang menghubungkan versi Indonesia dan Inggris (ADR-9).
 */
export function Seo({ title, description, image, path }: { title: string; description?: string | null; image?: string | null; path?: string }) {
    const { store, appUrl } = usePage().props;
    const base = String(appUrl ?? '');
    const cleanPath = (path ?? '').replace(/^\/en(?=\/|$)/, '') || '/';

    return (
        <Head title={title}>
            {description && <meta name="description" content={description} />}
            <meta property="og:site_name" content={`${store.name} · ${store.long_name}`} />
            <meta property="og:title" content={title} />
            {description && <meta property="og:description" content={description} />}
            {image && <meta property="og:image" content={image} />}
            <meta property="og:type" content="website" />
            {path !== undefined && <link rel="alternate" hrefLang="id" href={`${base}${cleanPath}`} />}
            {path !== undefined && <link rel="alternate" hrefLang="en" href={`${base}/en${cleanPath === '/' ? '' : cleanPath}`} />}
        </Head>
    );
}
