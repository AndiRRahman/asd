/** Harga selalu rupiah format Indonesia di semua bahasa (bagian 12.7). */
export function rupiah(amount: number | null | undefined): string {
    return `Rp ${new Intl.NumberFormat('id-ID').format(Math.round(amount ?? 0))}`;
}

/** Tanggal mengikuti bahasa yang dipilih, dalam zona WITA. */
export function formatDate(
    iso: string | null | undefined,
    locale: string,
    withTime = false,
): string {
    if (!iso) {
        return '-';
    }

    return new Intl.DateTimeFormat(locale === 'en' ? 'en-GB' : 'id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        timeZone: 'Asia/Makassar',
        ...(withTime ? { hour: '2-digit', minute: '2-digit' } : {}),
    }).format(new Date(iso));
}

export function grams(weight: number): string {
    return weight >= 1000
        ? `${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(weight / 1000)} kg`
        : `${weight} g`;
}

export function discountPercent(price: number, compare: number | null): number | null {
    return compare && compare > price
        ? Math.round(((compare - price) / compare) * 100)
        : null;
}
