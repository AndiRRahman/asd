import { Link } from '@inertiajs/react';
import { AlertTriangle, ArrowRight, Clock, Truck } from 'lucide-react';
import { Card, PanelPage, Pill, Stat, Table } from '@/components/panel/ui';
import { rupiah } from '@/lib/format';

type Props = {
    awaitingVerification: { code: string; buyer: string; grand_total: number; waiting_since: string | null; second_approval: boolean }[];
    readyToShip: { code: string; packages: number; pending: number; since: string }[];
    lowStock: { id: string; product: string; product_id: string; variant: string | null; sku: string; stock: number; threshold: number }[];
    revenue: { today: number; month: number; orders_today: number };
};

function age(iso: string | null): string {
    if (!iso) {
        return '-';
    }
    const hours = (Date.now() - new Date(iso).getTime()) / 3_600_000;

    return hours < 1 ? `${Math.round(hours * 60)} menit` : `${hours.toFixed(1).replace('.', ',')} jam`;
}

/** Halaman pertama Admin adalah antrean kerja: verifikasi tertua di atas (bagian 4). */
export default function Dashboard({ awaitingVerification, readyToShip, lowStock, revenue }: Props) {
    return (
        <PanelPage title="Antrean kerja" description="Pesanan yang menunggu verifikasi tampil paling atas, diurutkan dari yang paling lama menunggu.">
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Stat label="Menunggu verifikasi" value={awaitingVerification.length} tone={awaitingVerification.length ? 'warn' : 'default'} />
                <Stat label="Siap kirim" value={readyToShip.length} />
                <Stat label="Pendapatan hari ini" value={rupiah(revenue.today)} tone="good" />
                <Stat label="Pendapatan bulan ini" value={rupiah(revenue.month)} />
            </div>

            <Card title="1. Verifikasi pembayaran" actions={<Link href="/panel/pembayaran" className="flex items-center gap-1 text-sm font-semibold text-primary">Buka semua <ArrowRight className="size-4" /></Link>}>
                <Table head={['Kode', 'Pembeli', 'Tagihan', 'Menunggu', '']} empty="Tidak ada bukti transfer yang menunggu. Antrean bersih.">
                    {awaitingVerification.map((o) => (
                        <tr key={o.code} className="hover:bg-muted/40">
                            <td className="px-4 py-3 font-mono font-semibold">{o.code}</td>
                            <td className="px-4 py-3">{o.buyer}</td>
                            <td className="price px-4 py-3 font-semibold">{rupiah(o.grand_total)}</td>
                            <td className="px-4 py-3"><span className="flex items-center gap-1"><Clock className="size-3.5" /> {age(o.waiting_since)}</span> {o.second_approval && <Pill tone="warn">Perlu persetujuan kedua</Pill>}</td>
                            <td className="px-4 py-3 text-right"><Link href={`/panel/pembayaran/${o.code}`} className="font-semibold text-primary">Periksa →</Link></td>
                        </tr>
                    ))}
                </Table>
            </Card>

            <div className="grid gap-6 lg:grid-cols-2">
                <Card title="2. Siap dikirim">
                    <ul className="divide-y text-sm">
                        {readyToShip.length === 0 && <li className="py-6 text-center text-muted-foreground">Belum ada pesanan untuk dikirim.</li>}
                        {readyToShip.map((o) => (
                            <li key={o.code} className="flex items-center justify-between gap-3 py-3">
                                <span className="flex items-center gap-2"><Truck className="size-4 text-primary" /> <span className="font-mono font-semibold">{o.code}</span></span>
                                <span className="text-muted-foreground">{o.pending} dari {o.packages} paket belum dikirim</span>
                                <Link href={`/panel/pesanan/${o.code}`} className="font-semibold text-primary">Proses →</Link>
                            </li>
                        ))}
                    </ul>
                </Card>
                <Card title="3. Stok menipis">
                    <ul className="divide-y text-sm">
                        {lowStock.length === 0 && <li className="py-6 text-center text-muted-foreground">Semua stok di atas ambang.</li>}
                        {lowStock.map((v) => (
                            <li key={v.id} className="flex items-center justify-between gap-3 py-3">
                                <span className="min-w-0">
                                    <Link href={`/panel/produk/${v.product_id}/edit`} className="block truncate font-semibold hover:text-primary">{v.product}{v.variant && ` · ${v.variant}`}</Link>
                                    <span className="text-xs text-muted-foreground">{v.sku}</span>
                                </span>
                                <Pill tone={v.stock === 0 ? 'bad' : 'warn'}>{v.stock === 0 ? 'Habis' : `Sisa ${v.stock}`}</Pill>
                            </li>
                        ))}
                    </ul>
                    {lowStock.some((v) => v.stock === 0) && <p className="mt-3 flex items-center gap-2 text-xs text-muted-foreground"><AlertTriangle className="size-3.5" /> Produk habis tetap tampil di toko dengan tombol "Kabari saya".</p>}
                </Card>
            </div>
        </PanelPage>
    );
}
