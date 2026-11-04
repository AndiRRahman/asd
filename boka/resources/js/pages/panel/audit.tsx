import { PanelPage, Pager, SearchForm, Table } from '@/components/panel/ui';
import { formatDate } from '@/lib/format';
import type { Paginated } from '@/types/boka';

type Log = { id: number; description: string; subject: string | null; causer: string; changes: unknown; at: string };

/** Log audit: siapa mengubah apa, kapan, nilai lama dan baru. Tidak bisa dihapus dari antarmuka. */
export default function Audit({ logs }: { logs: Paginated<Log> }) {
    return (
        <PanelPage title="Log audit" description="Admin melihat aksinya sendiri; Super Admin melihat semua." actions={<SearchForm placeholder="Cari aksi" />}>
            <Table head={['Waktu', 'Pengguna', 'Aksi', 'Objek', 'Perubahan']}>
                {logs.data.map((l) => (
                    <tr key={l.id} className="align-top">
                        <td className="px-4 py-2 whitespace-nowrap">{formatDate(l.at, 'id', true)}</td>
                        <td className="px-4 py-2">{l.causer}</td>
                        <td className="px-4 py-2 font-semibold">{l.description}</td>
                        <td className="px-4 py-2">{l.subject ?? '-'}</td>
                        <td className="px-4 py-2"><pre className="max-w-xl overflow-x-auto text-[11px] whitespace-pre-wrap text-muted-foreground">{l.changes ? JSON.stringify(l.changes, null, 1) : '-'}</pre></td>
                    </tr>
                ))}
            </Table>
            <Pager current={logs.current_page} last={logs.last_page} />
        </PanelPage>
    );
}
