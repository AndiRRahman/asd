import { router } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useState } from 'react';
import { Card, PanelPage, Pill, Stat, Table, fieldClass } from '@/components/panel/ui';
import { Button } from '@/components/ui/button';
import { rupiah } from '@/lib/format';

type Props = {
    filters: { from: string; to: string };
    sales: { date: string; orders: number; revenue: number; average: number }[];
    producers: { producer: string; type: string; items: number; orders: number; revenue: number }[];
    topProducts: { product: string; qty: number; revenue: number }[];
    searchesWithoutResults: { query: string; total: number; last_searched: string }[];
    popularSearches: { query: string; total: number }[];
    lowStock: { sku: string; product: string; variant: string | null; origin: string | null; stock: number; threshold: number }[];
    vouchers: { code: string; status: string; total: number }[];
    canExportPersonal: boolean;
};

/**
 * Satu seri (omzet per hari): satu warna, tanpa legenda, judul yang menamainya.
 * Batang tipis, ujung atas membulat 4px menempel ke garis dasar, celah 2px, tooltip per batang.
 */
function RevenueChart({ data }: { data: Props['sales'] }) {
    const [hover, setHover] = useState<number | null>(null);
    const width = 720;
    const height = 220;
    const pad = { top: 16, right: 8, bottom: 28, left: 8 };
    const max = Math.max(1, ...data.map((d) => d.revenue));
    const slot = (width - pad.left - pad.right) / Math.max(1, data.length);
    const bar = Math.max(4, Math.min(28, slot - 2));
    const plotH = height - pad.top - pad.bottom;

    if (data.length === 0) {
        return <p className="py-10 text-center text-sm text-muted-foreground">Belum ada penjualan pada periode ini.</p>;
    }

    return (
        <div className="relative">
            <svg viewBox={`0 0 ${width} ${height}`} className="w-full" role="img" aria-label="Grafik omzet per hari">
                {[0.5, 1].map((f) => (
                    <line key={f} x1={pad.left} x2={width - pad.right} y1={pad.top + plotH * (1 - f)} y2={pad.top + plotH * (1 - f)} className="stroke-border" strokeDasharray="3 4" />
                ))}
                <line x1={pad.left} x2={width - pad.right} y1={pad.top + plotH} y2={pad.top + plotH} className="stroke-border" />
                {data.map((d, i) => {
                    const h = Math.max(2, (d.revenue / max) * plotH);
                    const x = pad.left + i * slot + (slot - bar) / 2;
                    const y = pad.top + plotH - h;
                    const r = Math.min(4, bar / 2, h);

                    return (
                        <g key={d.date} onMouseEnter={() => setHover(i)} onMouseLeave={() => setHover(null)}>
                            {/* Area sentuh lebih besar dari batang. */}
                            <rect x={pad.left + i * slot} y={pad.top} width={slot} height={plotH} fill="transparent" />
                            <path d={`M${x},${y + h} V${y + r} Q${x},${y} ${x + r},${y} H${x + bar - r} Q${x + bar},${y} ${x + bar},${y + r} V${y + h} Z`} className={hover === null || hover === i ? 'fill-primary' : 'fill-primary/40'} />
                            {(i === 0 || i === data.length - 1 || data.length <= 10) && (
                                <text x={x + bar / 2} y={height - 8} textAnchor="middle" className="fill-muted-foreground text-[10px]">{d.date.slice(5)}</text>
                            )}
                        </g>
                    );
                })}
            </svg>
            {hover !== null && (
                <div className="pointer-events-none absolute top-0 rounded-lg border bg-popover px-3 py-2 text-xs shadow-lg" style={{ left: `${((hover + 0.5) / data.length) * 100}%`, transform: 'translateX(-50%)' }}>
                    <p className="font-semibold">{data[hover].date}</p>
                    <p className="price">{rupiah(data[hover].revenue)} · {data[hover].orders} pesanan</p>
                </div>
            )}
        </div>
    );
}

export default function Reports(props: Props) {
    const [from, setFrom] = useState(props.filters.from);
    const [to, setTo] = useState(props.filters.to);
    const total = props.sales.reduce((s, d) => s + d.revenue, 0);
    const orders = props.sales.reduce((s, d) => s + d.orders, 0);
    const exportUrl = (report: string) => `/panel/laporan/ekspor/${report}?from=${props.filters.from}&to=${props.filters.to}`;

    return (
        <PanelPage
            title="Laporan"
            description="Semua waktu dalam WITA. Ekspor memakai filter yang sedang aktif, sehingga isi file sama dengan tabel di layar."
            actions={
                <form className="flex flex-wrap items-end gap-2" onSubmit={(e) => { e.preventDefault(); router.get('/panel/laporan', { from, to }); }}>
                    <input type="date" className={fieldClass} value={from} onChange={(e) => setFrom(e.target.value)} aria-label="Dari" />
                    <input type="date" className={fieldClass} value={to} onChange={(e) => setTo(e.target.value)} aria-label="Sampai" />
                    <Button type="submit">Terapkan</Button>
                </form>
            }
        >
            <div className="grid gap-4 sm:grid-cols-3">
                <Stat label="Omzet" value={rupiah(total)} tone="good" />
                <Stat label="Pesanan dibayar" value={orders} />
                <Stat label="Rata-rata nilai pesanan" value={rupiah(orders ? Math.round(total / orders) : 0)} />
            </div>

            <Card title="Omzet per hari" actions={<Button variant="outline" size="sm" asChild><a href={exportUrl('penjualan')}><Download /> CSV</a></Button>}>
                <RevenueChart data={props.sales} />
            </Card>

            <Card title="Penjualan per produsen (bahan pembagian hasil)" actions={<Button variant="outline" size="sm" asChild><a href={exportUrl('produsen')}><Download /> CSV</a></Button>}>
                <Table head={['Produsen', 'Jenis', 'Item terjual', 'Pesanan', 'Omzet']}>
                    {props.producers.map((p) => (
                        <tr key={p.producer}><td className="px-4 py-2 font-semibold">{p.producer}</td><td className="px-4 py-2">{p.type === 'karang_taruna' ? 'Karang Taruna' : 'UMKM'}</td><td className="price px-4 py-2">{p.items}</td><td className="price px-4 py-2">{p.orders}</td><td className="price px-4 py-2 font-bold">{rupiah(p.revenue)}</td></tr>
                    ))}
                </Table>
            </Card>

            <div className="grid gap-6 lg:grid-cols-2">
                <Card title="Kata kunci tanpa hasil" actions={<Button variant="outline" size="sm" asChild><a href={exportUrl('pencarian-tanpa-hasil')}><Download /> CSV</a></Button>}>
                    <p className="mb-3 text-sm text-muted-foreground">Barang yang dicari warga tetapi belum dijual. Teruskan ke Karang Taruna dan UMKM.</p>
                    <ul className="divide-y text-sm">
                        {props.searchesWithoutResults.length === 0 && <li className="py-4 text-muted-foreground">Semua pencarian menemukan produk.</li>}
                        {props.searchesWithoutResults.map((s) => <li key={s.query} className="flex justify-between py-2"><span className="font-semibold">{s.query}</span><Pill tone="warn">{s.total}× dicari</Pill></li>)}
                    </ul>
                </Card>
                <Card title="Produk terlaris">
                    <ul className="divide-y text-sm">
                        {props.topProducts.map((p) => <li key={p.product} className="flex justify-between gap-2 py-2"><span>{p.product}</span><span className="price">{p.qty} terjual · {rupiah(p.revenue)}</span></li>)}
                    </ul>
                </Card>
                <Card title="Kata kunci populer">
                    <div className="flex flex-wrap gap-2">{props.popularSearches.map((s) => <Pill key={s.query} tone="primary">{s.query} · {s.total}</Pill>)}</div>
                </Card>
                <Card title="Stok menipis dan habis" actions={<Button variant="outline" size="sm" asChild><a href={exportUrl('stok-menipis')}><Download /> CSV</a></Button>}>
                    <ul className="divide-y text-sm">
                        {props.lowStock.map((v) => <li key={v.sku} className="flex justify-between gap-2 py-2"><span>{v.product}{v.variant && ` · ${v.variant}`} <span className="text-xs text-muted-foreground">{v.origin}</span></span><Pill tone={v.stock === 0 ? 'bad' : 'warn'}>{v.stock}</Pill></li>)}
                    </ul>
                </Card>
            </div>

            <Card title="Ekspor daftar pesanan" actions={<Button variant="outline" size="sm" asChild><a href={exportUrl('pesanan')}><Download /> CSV</a></Button>}>
                <p className="text-sm text-muted-foreground">
                    {props.canExportPersonal ? 'Anda Admin Transaksi/Super Admin: nama dan WhatsApp Pembeli tampil lengkap.' : 'Nama dan WhatsApp Pembeli disamarkan (contoh 0812****4567) untuk peran Anda.'} Setiap ekspor tercatat di log audit.
                </p>
            </Card>
        </PanelPage>
    );
}
