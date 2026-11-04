import { router } from '@inertiajs/react';
import { PanelPage, Pager, Pill, SearchForm, Table } from '@/components/panel/ui';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';
import type { Paginated } from '@/types/boka';

type Customer = {
    id: string; name: string; email: string; phone: string; status: string; orders: number; blocked_until: string | null; joined: string;
    risk: { expired: number; rejected_proofs: number; duplicate_proofs: number; shared_address_accounts: number; level: string };
};

/** Daftar Pembeli dengan panel sinyal risiko berbasis aturan (11.1 T13). */
export default function Customers({ customers, q }: { customers: Paginated<Customer>; q: string | null }) {
    return (
        <PanelPage title="Pelanggan" description="Nomor WhatsApp tersamar untuk Admin tanpa izin data pribadi." actions={<SearchForm value={q} placeholder="Nama atau email" />}>
            <Table head={['Nama', 'Kontak', 'Pesanan', 'Sinyal risiko', 'Status', 'Bergabung', '']}>
                {customers.data.map((c) => (
                    <tr key={c.id}>
                        <td className="px-4 py-3 font-semibold">{c.name}</td>
                        <td className="px-4 py-3">{c.email}<br /><span className="text-xs text-muted-foreground">{c.phone}</span></td>
                        <td className="px-4 py-3">{c.orders}</td>
                        <td className="px-4 py-3">
                            <Pill tone={c.risk.level === 'tinggi' ? 'bad' : c.risk.level === 'sedang' ? 'warn' : 'good'}>{c.risk.level}</Pill>
                            <span className="ml-2 text-xs text-muted-foreground">{c.risk.expired} kedaluwarsa · {c.risk.rejected_proofs} ditolak · {c.risk.duplicate_proofs} bukti ganda · {c.risk.shared_address_accounts} akun sealamat</span>
                        </td>
                        <td className="px-4 py-3">
                            <Pill tone={c.status === 'aktif' ? 'good' : 'muted'}>{c.status}</Pill>
                            {c.blocked_until && <Pill tone="bad">checkout diblokir</Pill>}
                        </td>
                        <td className="px-4 py-3 whitespace-nowrap">{formatDate(c.joined, 'id')}</td>
                        <td className="px-4 py-3">
                            <div className="flex justify-end gap-2">
                                <Button size="sm" variant="outline" onClick={() => router.post(`/panel/pelanggan/${c.id}/blokir-checkout`, { block: !c.blocked_until }, { preserveScroll: true })}>{c.blocked_until ? 'Buka blokir' : 'Blokir checkout'}</Button>
                                <Button size="sm" variant="ghost" onClick={() => router.post(`/panel/pelanggan/${c.id}/status`, {}, { preserveScroll: true })}>{c.status === 'aktif' ? 'Nonaktifkan' : 'Aktifkan'}</Button>
                            </div>
                        </td>
                    </tr>
                ))}
            </Table>
            <Pager current={customers.current_page} last={customers.last_page} />
        </PanelPage>
    );
}
