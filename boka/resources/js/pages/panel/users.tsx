import { router, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Card, PanelPage, Pill, Table, fieldClass } from '@/components/panel/ui';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';

type Props = {
    users: { id: string; name: string; email: string; status: string; role: string; two_factor: boolean; last_login_at: string | null }[];
    invitations: { id: number; email: string; role: string; expires_at: string }[];
};

const ROLES = { admin: 'Admin', admin_transaksi: 'Admin Transaksi', super_admin: 'Super Admin' };

export default function Users({ users, invitations }: Props) {
    const invite = useForm({ email: '', role: 'admin' });

    return (
        <PanelPage title="Pengguna panel" description="Admin dibuat lewat undangan email bertanda tangan (berlaku 48 jam). Password tidak pernah dikirim lewat email. Siapkan minimal dua Super Admin dan dua Admin Transaksi.">
            <Card title="Undang Admin">
                <form className="flex flex-wrap gap-2" onSubmit={(e) => { e.preventDefault(); invite.post('/panel/pengguna/undang', { onSuccess: () => invite.reset('email') }); }}>
                    <input type="email" className={`${fieldClass} max-w-sm`} placeholder="email@contoh.id" value={invite.data.email} onChange={(e) => invite.setData('email', e.target.value)} />
                    <select className={`${fieldClass} max-w-48`} value={invite.data.role} onChange={(e) => invite.setData('role', e.target.value)}>
                        {Object.entries(ROLES).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                    </select>
                    <Button type="submit" disabled={invite.processing}>Kirim undangan</Button>
                </form>
                <InputError message={invite.errors.email} />
                {invitations.length > 0 && <p className="mt-3 text-sm text-muted-foreground">Menunggu diterima: {invitations.map((i) => `${i.email} (${ROLES[i.role as keyof typeof ROLES]})`).join(', ')}</p>}
            </Card>
            <Table head={['Nama', 'Email', 'Peran', '2FA', 'Status', 'Login terakhir', '']}>
                {users.map((u) => (
                    <tr key={u.id}>
                        <td className="px-4 py-3 font-semibold">{u.name}</td>
                        <td className="px-4 py-3">{u.email}</td>
                        <td className="px-4 py-3">
                            <select className="h-10 rounded-lg border border-input bg-card px-2" value={u.role} onChange={(e) => router.post(`/panel/pengguna/${u.id}`, { role: e.target.value }, { preserveScroll: true })}>
                                {Object.entries(ROLES).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                            </select>
                        </td>
                        <td className="px-4 py-3">{u.two_factor ? <Pill tone="good">Aktif</Pill> : <Pill tone="bad">Belum</Pill>}</td>
                        <td className="px-4 py-3"><Pill tone={u.status === 'aktif' ? 'good' : 'muted'}>{u.status}</Pill></td>
                        <td className="px-4 py-3">{formatDate(u.last_login_at, 'id', true)}</td>
                        <td className="px-4 py-3">
                            <div className="flex justify-end gap-2">
                                <Button size="sm" variant="outline" onClick={() => router.post(`/panel/pengguna/${u.id}`, { status: u.status === 'aktif' ? 'nonaktif' : 'aktif' }, { preserveScroll: true })}>{u.status === 'aktif' ? 'Nonaktifkan' : 'Aktifkan'}</Button>
                                {u.two_factor && <Button size="sm" variant="ghost" onClick={() => router.post(`/panel/pengguna/${u.id}`, { reset_2fa: true }, { preserveScroll: true })}>Reset 2FA</Button>}
                            </div>
                        </td>
                    </tr>
                ))}
            </Table>
        </PanelPage>
    );
}
