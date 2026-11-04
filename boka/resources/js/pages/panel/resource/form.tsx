import { useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import InputError from '@/components/input-error';
import { Card, PanelPage, fieldClass } from '@/components/panel/ui';
import { Button } from '@/components/ui/button';
import type { Field, ResourceMeta } from './index';

type FormValue = string | number | boolean | null | File | Record<string, string> | Record<string, string | number>[];
type Values = Record<string, FormValue>;

function initial(fields: Field[]): Values {
    return Object.fromEntries(fields.map((f) => [f.name, f.type === 'translatable' ? { id: '', en: '' } : f.type === 'boolean' ? true : f.type === 'repeater' ? [] : f.type === 'image' ? null : '']));
}

function Input({ field, value, onChange, error }: { field: Field; value: unknown; onChange: (v: unknown) => void; error?: string }) {
    const text = (v: unknown) => (v === null || v === undefined ? '' : String(v));

    switch (field.type) {
        case 'translatable': {
            const tr = (value as { id?: string; en?: string }) ?? {};
            const Tag = field.multiline ? 'textarea' : 'input';

            return (
                <div className="grid gap-2 sm:grid-cols-2">
                    {(['id', 'en'] as const).map((lang) => (
                        <label key={lang} className="grid gap-1 text-xs text-muted-foreground">
                            {lang === 'id' ? 'Indonesia (wajib)' : 'Inggris (opsional)'}
                            <Tag rows={field.rows ?? 3} className={field.multiline ? 'rounded-lg border border-input bg-card p-3 text-foreground' : fieldClass} value={tr[lang] ?? ''} onChange={(e: React.ChangeEvent<HTMLInputElement & HTMLTextAreaElement>) => onChange({ ...tr, [lang]: e.target.value })} />
                        </label>
                    ))}
                </div>
            );
        }
        case 'textarea':
            return <textarea rows={field.rows ?? 3} className="w-full rounded-lg border border-input bg-card p-3" value={text(value)} onChange={(e) => onChange(e.target.value)} />;
        case 'select':
            return (
                <select className={fieldClass} value={text(value)} onChange={(e) => onChange(e.target.value === '' ? null : e.target.value)}>
                    <option value="">Pilih</option>
                    {field.options?.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                </select>
            );
        case 'boolean':
            return <input type="checkbox" className="size-5 accent-[var(--primary)]" checked={Boolean(value)} onChange={(e) => onChange(e.target.checked)} />;
        case 'number':
        case 'money':
            return <input type="number" min={0} className={fieldClass} value={text(value)} onChange={(e) => onChange(e.target.value === '' ? null : Number(e.target.value))} />;
        case 'datetime':
            return <input type="datetime-local" className={fieldClass} value={text(value)} onChange={(e) => onChange(e.target.value || null)} />;
        case 'image':
            return (
                <div className="flex items-center gap-3">
                    {typeof value === 'string' && value && <img src={value} alt="" className="size-16 rounded-lg object-cover" />}
                    <input type="file" accept="image/*" onChange={(e) => onChange(e.target.files?.[0] ?? null)} className="text-sm" />
                </div>
            );
        case 'repeater': {
            const rows = (value as Record<string, string | number>[]) ?? [];

            return (
                <div className="space-y-2">
                    {rows.map((row, i) => (
                        <div key={i} className="flex flex-wrap items-end gap-2 rounded-lg border p-3">
                            {field.fields?.map((sub) => (
                                <label key={sub.name} className="grid min-w-40 flex-1 gap-1 text-xs font-semibold">
                                    {sub.label}
                                    <Input field={sub} value={row[sub.name]} onChange={(v) => onChange(rows.map((r, k) => (k === i ? { ...r, [sub.name]: v as string } : r)))} />
                                </label>
                            ))}
                            <Button type="button" variant="ghost" size="icon" onClick={() => onChange(rows.filter((_, k) => k !== i))} aria-label="Hapus baris"><Trash2 /></Button>
                        </div>
                    ))}
                    <Button type="button" variant="outline" size="sm" onClick={() => onChange([...rows, Object.fromEntries((field.fields ?? []).map((f) => [f.name, '']))])}><Plus /> Tambah baris</Button>
                    {error && <InputError message={error} />}
                </div>
            );
        }
        default:
            return <input className={fieldClass} value={text(value)} onChange={(e) => onChange(e.target.value)} />;
    }
}

export default function ResourceForm({ resource, values, id }: { resource: ResourceMeta; values: Values | null; id: string | null }) {
    const form = useForm<Values>({ ...initial(resource.fields), ...(values ?? {}) });
    const errorFor = (name: string) => Object.entries(form.errors as Record<string, string>).find(([key]) => key === name || key.startsWith(`${name}.`))?.[1];

    return (
        <PanelPage title={`${id ? 'Ubah' : 'Tambah'} ${resource.title.toLowerCase()}`}>
            <form onSubmit={(e) => { e.preventDefault(); form.transform((d) => Object.fromEntries(Object.entries(d).filter(([k, v]) => !(resource.fields.find((f) => f.name === k)?.type === 'image' && typeof v === 'string')))); form.post(id ? `/panel/kelola/${resource.slug}/${id}` : `/panel/kelola/${resource.slug}`, { forceFormData: true }); }}>
                <Card>
                    <div className="grid max-w-3xl gap-5">
                        {resource.fields.map((field) => (
                            <div key={field.name} className={field.type === 'boolean' ? 'flex items-center gap-3' : 'grid gap-1.5'}>
                                <label className="text-sm font-semibold">{field.label}</label>
                                <Input field={field} value={form.data[field.name]} onChange={(v) => form.setData(field.name, v as FormValue)} error={errorFor(field.name)} />
                                {field.type !== 'repeater' && <InputError message={errorFor(field.name)} />}
                            </div>
                        ))}
                        <div><Button type="submit" size="lg" disabled={form.processing}>Simpan</Button></div>
                    </div>
                </Card>
            </form>
        </PanelPage>
    );
}
