import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Card, PanelPage, Pill, fieldClass } from '@/components/panel/ui';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';

type Props = {
    twoFactor: boolean;
    settings: { key: string; label: string; value: number }[];
    banks: { id: string; bank_name: string; account_number: string; account_holder: string; is_active: boolean; active_from: string; waiting: boolean }[];
    synonyms: string;
};

/** Rekening tujuan: password (+ kode 2FA jika aktif), email ke semua Super Admin, masa tunggu 24 jam (11.1 T4). */
export default function Settings({ twoFactor, settings, banks, synonyms }: Props) {
    const general = useForm({ settings: Object.fromEntries(settings.map((s) => [s.key, s.value])), synonyms });
    const bank = useForm({ bank_name: '', account_number: '', account_holder: '', password: '', code: '' });
    const [toggle, setToggle] = useState<{ id: string; password: string; code: string } | null>(null);

    return (
        <PanelPage title="Pengaturan" description="Semua perubahan tercatat di log audit dengan nilai lama dan baru.">
            <Card title="Aturan toko">
                <form className="grid gap-4 sm:grid-cols-2" onSubmit={(e) => { e.preventDefault(); general.post('/panel/pengaturan', { preserveScroll: true }); }}>
                    {settings.map((s) => (
                        <label key={s.key} className="grid gap-1 text-sm font-semibold">
                            {s.label}
                            <input type="number" min={0} className={fieldClass} value={general.data.settings[s.key]} onChange={(e) => general.setData('settings', { ...general.data.settings, [s.key]: Number(e.target.value) })} />
                        </label>
                    ))}
                    <label className="grid gap-1 text-sm font-semibold sm:col-span-2">
                        Sinonim pencarian (satu kelompok per baris, pisahkan dengan koma)
                        <textarea rows={4} className="rounded-lg border border-input bg-card p-3 font-normal" value={general.data.synonyms} onChange={(e) => general.setData('synonyms', e.target.value)} placeholder={'sutra, sutera\nkopi, coffee'} />
                    </label>
                    <div><Button type="submit" disabled={general.processing}>Simpan pengaturan</Button></div>
                </form>
            </Card>

            <Card title="Rekening tujuan transfer">
                <ul className="mb-6 divide-y text-sm">
                    {banks.map((b) => (
                        <li key={b.id} className="flex flex-wrap items-center justify-between gap-3 py-3">
                            <span><b>{b.bank_name} {b.account_number}</b><br />a.n. {b.account_holder}</span>
                            <span className="flex items-center gap-2">
                                {!b.is_active ? <Pill>Nonaktif</Pill> : b.waiting ? <Pill tone="warn">Tampil mulai {formatDate(b.active_from, 'id', true)}</Pill> : <Pill tone="good">Aktif</Pill>}
                                <Button size="sm" variant="outline" onClick={() => setToggle({ id: b.id, password: '', code: '' })}>{b.is_active ? 'Nonaktifkan' : 'Aktifkan'}</Button>
                            </span>
                            {toggle?.id === b.id && (
                                <form className="flex w-full flex-wrap gap-2" onSubmit={(e) => { e.preventDefault(); router.post(`/panel/rekening/${b.id}/status`, { password: toggle.password, code: toggle.code }, { preserveScroll: true, onSuccess: () => setToggle(null) }); }}>
                                    <input type="password" className={`${fieldClass} max-w-48`} placeholder="Password" value={toggle.password} onChange={(e) => setToggle({ ...toggle, password: e.target.value })} />
                                    {twoFactor && <input inputMode="numeric" className={`${fieldClass} max-w-36`} placeholder="Kode 2FA" value={toggle.code} onChange={(e) => setToggle({ ...toggle, code: e.target.value })} />}
                                    <Button type="submit" size="sm">Konfirmasi</Button>
                                </form>
                            )}
                        </li>
                    ))}
                </ul>
                <form className="grid gap-3 sm:grid-cols-3" onSubmit={(e) => { e.preventDefault(); bank.post('/panel/rekening', { preserveScroll: true, onSuccess: () => bank.reset() }); }}>
                    <input className={fieldClass} placeholder="Nama bank" value={bank.data.bank_name} onChange={(e) => bank.setData('bank_name', e.target.value)} />
                    <input className={fieldClass} placeholder="Nomor rekening" value={bank.data.account_number} onChange={(e) => bank.setData('account_number', e.target.value)} />
                    <input className={fieldClass} placeholder="Atas nama (rekening organisasi)" value={bank.data.account_holder} onChange={(e) => bank.setData('account_holder', e.target.value)} />
                    <input type="password" className={fieldClass} placeholder="Password Anda" value={bank.data.password} onChange={(e) => bank.setData('password', e.target.value)} autoComplete="current-password" />
                    {twoFactor && <input inputMode="numeric" className={fieldClass} placeholder="Kode 2FA" value={bank.data.code} onChange={(e) => bank.setData('code', e.target.value)} />}
                    <Button type="submit" disabled={bank.processing}>Tambah rekening</Button>
                    <div className="sm:col-span-3"><InputError message={Object.values(bank.errors)[0]} /></div>
                </form>
                <p className="mt-3 text-xs text-muted-foreground">Rekening baru tampil di checkout 24 jam setelah ditambahkan. Pesanan lama tetap memakai rekening yang tercatat saat dipesan.</p>
            </Card>
        </PanelPage>
    );
}
