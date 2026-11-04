import { Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { ConfirmButton, LinkButton, PanelPage, Pager, Pill, SearchForm, Table } from '@/components/panel/ui';
import { formatDate } from '@/lib/format';
import type { Paginated } from '@/types/boka';

export type ResourceMeta = {
    slug: string;
    title: string;
    columns: { key: string; label: string; type?: string }[];
    fields: Field[];
};

export type Field = {
    name: string;
    label: string;
    type: 'text' | 'textarea' | 'number' | 'money' | 'select' | 'boolean' | 'datetime' | 'translatable' | 'image' | 'repeater';
    options?: { value: string | number; label: string }[];
    multiline?: boolean;
    rows?: number;
    fields?: Field[];
};

type Row = Record<string, unknown> & { id: string; _deletable: boolean };

function cell(value: unknown, type?: string) {
    if (type === 'boolean') {
        return value ? <Pill tone="good">Ya</Pill> : <Pill>Tidak</Pill>;
    }
    if (type === 'datetime') {
        return formatDate(value as string | null, 'id', true);
    }

    return value === null || value === undefined || value === '' ? '-' : String(value);
}

export default function ResourceIndex({ resource, rows, q }: { resource: ResourceMeta; rows: Paginated<Row> & { total: number }; q: string | null }) {
    return (
        <PanelPage title={resource.title} description={`${rows.total} data`} actions={<><SearchForm value={q} placeholder="Cari" /><LinkButton href={`/panel/kelola/${resource.slug}/baru`}><Plus /> Tambah</LinkButton></>}>
            <Table head={[...resource.columns.map((c) => c.label), '']}>
                {rows.data.map((row) => (
                    <tr key={row.id} className="hover:bg-muted/40">
                        {resource.columns.map((c) => <td key={c.key} className="px-4 py-3">{cell(row[c.key], c.type)}</td>)}
                        <td className="px-4 py-3">
                            <div className="flex justify-end gap-2">
                                <Link href={`/panel/kelola/${resource.slug}/${row.id}`} className="font-semibold text-primary">Ubah</Link>
                                {row._deletable && (
                                    <ConfirmButton variant="outline" label="Hapus" title="Hapus data ini?" body="Data yang dihapus tidak bisa dikembalikan." onConfirm={() => router.delete(`/panel/kelola/${resource.slug}/${row.id}`)} />
                                )}
                            </div>
                        </td>
                    </tr>
                ))}
            </Table>
            <Pager current={rows.current_page} last={rows.last_page} />
        </PanelPage>
    );
}
