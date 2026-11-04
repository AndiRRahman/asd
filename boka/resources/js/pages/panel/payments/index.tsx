import { Link } from '@inertiajs/react';
import { PanelPage, Pill, Table } from '@/components/panel/ui';
import { formatDate, rupiah } from '@/lib/format';

type Row = { code: string; buyer: string; grand_total: number; bank: string | null; sender: string | null; waiting_since: string | null; stage: string | null };

export default function Payments({ orders }: { orders: Row[] }) {
    return (
        <PanelPage title="Verifikasi pembayaran" description="Pembayaran hanya sah jika dana terlihat di mutasi rekening tujuan. Gambar bukti hanya alat bantu pencocokan.">
            <Table head={['Kode', 'Pembeli', 'Tagihan', 'Rekening tujuan', 'Pengirim', 'Bukti masuk', 'Tahap', '']} empty="Tidak ada pembayaran yang menunggu verifikasi.">
                {orders.map((o) => (
                    <tr key={o.code} className="hover:bg-muted/40">
                        <td className="px-4 py-3 font-mono font-semibold">{o.code}</td>
                        <td className="px-4 py-3">{o.buyer}</td>
                        <td className="price px-4 py-3 font-bold">{rupiah(o.grand_total)}</td>
                        <td className="px-4 py-3">{o.bank}</td>
                        <td className="px-4 py-3">{o.sender}</td>
                        <td className="px-4 py-3 whitespace-nowrap">{formatDate(o.waiting_since, 'id', true)}</td>
                        <td className="px-4 py-3">{o.stage === 'disetujui_1' ? <Pill tone="warn">Persetujuan kedua</Pill> : <Pill>Baru</Pill>}</td>
                        <td className="px-4 py-3 text-right"><Link className="font-semibold text-primary" href={`/panel/pembayaran/${o.code}`}>Periksa →</Link></td>
                    </tr>
                ))}
            </Table>
        </PanelPage>
    );
}
