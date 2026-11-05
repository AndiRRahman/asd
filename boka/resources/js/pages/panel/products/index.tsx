import { Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { LinkButton, PanelPage, Pager, Pill, SearchForm, Table } from '@/components/panel/ui';
import { rupiah } from '@/lib/format';
import type { Paginated } from '@/types/boka';

type Row = { id: string; name: string; slug: string; status: string; image: string | null; producer: string | null; category: string | null; stock: number; variants: number; price_min: number | null; low: boolean };

const STATUS_TONE = { draf: 'warn', aktif: 'good', nonaktif: 'muted', arsip: 'muted' } as const;

export default function Products({ products, filters }: { products: Paginated<Row>; filters: { q?: string; status?: string } }) {
    return (
        <PanelPage
            title="Produk"
            description="Hanya produk berstatus Aktif yang tampil di toko. Arsip menggantikan hapus untuk produk yang pernah dipesan."
            actions={<><SearchForm value={filters.q} placeholder="Cari nama produk" extra={{ status: filters.status }} /><LinkButton href="/panel/produk/baru"><Plus /> Produk baru</LinkButton></>}
        >
            <div className="flex gap-2">
                {['', 'draf', 'aktif', 'nonaktif', 'arsip'].map((s) => (
                    <button key={s || 'semua'} type="button" onClick={() => router.get('/panel/produk', { status: s || undefined, q: filters.q })} className={`rounded-full border px-4 py-2 text-sm font-semibold capitalize ${(filters.status ?? '') === s ? 'border-primary bg-primary text-primary-foreground' : ''}`}>{s || 'Semua'}</button>
                ))}
            </div>
            <Table head={['', 'Produk', 'Produsen', 'Kategori', 'Harga mulai', 'Stok', 'Status', '']}>
                {products.data.map((p) => (
                    <tr key={p.id} className="hover:bg-muted/40">
                        <td className="px-4 py-2">{p.image && <img src={p.image} alt="" className="size-12 rounded-md object-cover" />}</td>
                        <td className="px-4 py-2 font-semibold">{p.name}<span className="block text-xs font-normal text-muted-foreground">{p.variants} varian</span></td>
                        <td className="px-4 py-2">{p.producer}</td>
                        <td className="px-4 py-2">{p.category}</td>
                        <td className="price px-4 py-2">{p.price_min ? rupiah(p.price_min) : '-'}</td>
                        <td className="px-4 py-2">{p.low ? <Pill tone={p.stock === 0 ? 'bad' : 'warn'}>{p.stock}</Pill> : p.stock}</td>
                        <td className="px-4 py-2"><Pill tone={STATUS_TONE[p.status as keyof typeof STATUS_TONE]}>{p.status}</Pill></td>
                        <td className="px-4 py-2 text-right"><Link href={`/panel/produk/${p.id}/edit`} className="font-semibold text-primary">Ubah →</Link></td>
                    </tr>
                ))}
            </Table>
            <Pager current={products.current_page} last={products.last_page} />
        </PanelPage>
    );
}
