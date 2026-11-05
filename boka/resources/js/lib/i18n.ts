import { usePage } from '@inertiajs/react';

type Replacements = Record<string, string | number>;

/**
 * Teks antarmuka berasal dari lang/{id,en}.json yang dikirim server (bagian 12.7).
 * Kunci yang belum diterjemahkan tampil apa adanya agar mudah terlihat saat pengujian.
 */
export function useT() {
    const { translations } = usePage().props;

    return (key: string, replacements: Replacements | string = {}): string => {
        if (typeof replacements === 'string') {
            return translations[key] ?? replacements;
        }

        let text = translations[key] ?? key;

        for (const [name, value] of Object.entries(replacements)) {
            text = text.replaceAll(`:${name}`, String(value));
        }

        return text;
    };
}

/** URL halaman konten mengikuti bahasa aktif: "/" Indonesia, "/en/..." Inggris (ADR-9). */
export function useLocalePath() {
    const { locale } = usePage().props;

    return (path: string): string => {
        if (locale !== 'en') {
            return path;
        }

        const localized = /^\/(produk|kategori|produsen|kabar|halaman|rekening-resmi)?(\/|\?|$)/.test(path);

        return localized ? `/en${path === '/' ? '' : path}` : path;
    };
}
