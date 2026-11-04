import { router, useForm } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, XCircle } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Card, ConfirmButton, OrderStatusPill, PanelPage, Pill, fieldClass } from '@/components/panel/ui';
import { Button } from '@/components/ui/button';
import { formatDate, rupiah } from '@/lib/format';
import type { OrderDetail, OrderSummary } from '@/types/boka';

type Props = {
    order: OrderDetail;
    buyer: { name: string; email: string; phone: string; orders: OrderSummary[]; risk: Record<string, number | string> };
    proof: {
        id: string; url: string; is_pdf: boolean; sender_bank: string; sender_name: string; transferred_at: string | null; status: string;
        first_approver: string | null; first_approver_is_me: boolean; mutation_datetime: string | null; duplicates: string[];
    } | null;
    templates: string[];
    threshold: number;
    canVerify: boolean;
};

/**
 * Satu keputusan dalam satu layar (12.5). Tombol Setujui baru aktif setelah daftar periksa mutasi terisi (11.1 T1, K27).
 */
export default function PaymentShow({ order, buyer, proof, templates, threshold, canVerify }: Props) {
    const approve = useForm({ mutation_checked: false, mutation_datetime: proof?.mutation_datetime ?? '' });
    const [reason, setReason] = useState(templates[0]);
    const [custom, setCustom] = useState('');
    const isSecond = proof?.status === 'disetujui_1';
    const needsSecond = order.grand_total > threshold;

    return (
        <PanelPage title={`Verifikasi ${order.code}`} description="Bandingkan bukti dengan tagihan, lalu cocokkan nominal tepat di mutasi rekening tujuan." actions={<OrderStatusPill status={order.status} />}>
            {proof && proof.duplicates.length > 0 && (
                <p role="alert" className="flex items-center gap-2 rounded-xl border-2 border-destructive bg-destructive/10 p-4 font-semibold text-destructive">
                    <AlertTriangle className="size-5" /> Bukti ini pernah dipakai di pesanan {proof.duplicates.join(', ')}. Periksa dengan teliti.
                </p>
            )}

            <div className="grid gap-6 xl:grid-cols-2">
                <Card title="Bukti transfer">
                    {proof ? (
                        <>
                            {proof.is_pdf ? (
                                <iframe src={proof.url} title="Bukti transfer" className="h-[520px] w-full rounded-lg border" />
                            ) : (
                                <a href={proof.url} target="_blank" rel="noopener"><img src={proof.url} alt="Bukti transfer" className="max-h-[520px] w-full rounded-lg border bg-muted object-contain" /></a>
                            )}
                            <dl className="mt-4 grid grid-cols-2 gap-3 text-sm">
                                <div><dt className="text-muted-foreground">Bank pengirim</dt><dd className="font-semibold">{proof.sender_bank}</dd></div>
                                <div><dt className="text-muted-foreground">Nama pemilik rekening</dt><dd className="font-semibold">{proof.sender_name}</dd></div>
                                <div><dt className="text-muted-foreground">Tanggal transfer</dt><dd className="font-semibold">{proof.transferred_at}</dd></div>
                                <div><dt className="text-muted-foreground">Tautan berlaku</dt><dd>10 menit (muat ulang jika kedaluwarsa)</dd></div>
                            </dl>
                        </>
                    ) : <p className="text-muted-foreground">Belum ada bukti.</p>}
                </Card>

                <div className="space-y-6">
                    <Card title="Tagihan">
                        <p className="price text-4xl font-extrabold">{rupiah(order.grand_total)}</p>
                        <p className="mt-1 text-sm text-muted-foreground">Kode unik <b className="text-foreground">{order.unique_code}</b> sudah termasuk. Nominal di mutasi harus sama persis.</p>
                        <div className="mt-4 rounded-xl bg-muted p-4 text-sm">
                            <p className="text-muted-foreground">Rekening tujuan</p>
                            <p className="font-bold">{order.bank.bank_name} {order.bank.account_number}</p>
                            <p>a.n. {order.bank.account_holder}</p>
                        </div>
                        {needsSecond && <p className="mt-3"><Pill tone="warn">Di atas {rupiah(threshold)}: butuh persetujuan dua orang</Pill></p>}
                        {isSecond && <p className="mt-2 text-sm">Persetujuan pertama oleh <b>{proof?.first_approver}</b>.</p>}
                    </Card>

                    {canVerify && order.status === 'menunggu_verifikasi' && (
                        <Card title={isSecond ? 'Persetujuan kedua' : 'Keputusan'}>
                            {proof?.first_approver_is_me ? (
                                <p className="rounded-lg bg-gold-soft p-3 text-sm">Anda sudah memberi persetujuan pertama. Persetujuan kedua harus oleh Admin Transaksi lain atau Super Admin.</p>
                            ) : (
                                <form className="space-y-4" onSubmit={(e) => { e.preventDefault(); approve.post(`/panel/pembayaran/${order.code}/setujui`); }}>
                                    <label className="flex items-start gap-3 rounded-xl border-2 border-primary/30 p-4 text-sm">
                                        <input type="checkbox" className="mt-0.5 size-5 accent-[var(--primary)]" checked={approve.data.mutation_checked} onChange={(e) => approve.setData('mutation_checked', e.target.checked)} />
                                        <span><b>Saya sudah membuka mutasi rekening {order.bank.bank_name}</b> dan nominal tepat {rupiah(order.grand_total)} (termasuk kode unik) sudah masuk.</span>
                                    </label>
                                    <div className="grid gap-1.5">
                                        <label htmlFor="mutation_datetime" className="text-sm font-semibold">Tanggal dan jam mutasi (WITA)</label>
                                        <input id="mutation_datetime" type="datetime-local" className={fieldClass} value={approve.data.mutation_datetime} onChange={(e) => approve.setData('mutation_datetime', e.target.value)} />
                                        <InputError message={approve.errors.mutation_datetime ?? approve.errors.mutation_checked} />
                                    </div>
                                    <Button type="submit" size="lg" className="w-full" disabled={!approve.data.mutation_checked || !approve.data.mutation_datetime || approve.processing}>
                                        <CheckCircle2 /> {needsSecond && !isSecond ? 'Setujui (persetujuan pertama)' : 'Setujui pembayaran'}
                                    </Button>
                                </form>
                            )}

                            <div className="mt-6 space-y-3 border-t pt-5">
                                <p className="text-sm font-semibold">Tolak bukti {order.rejection_count === 0 ? '(Pembeli dapat batas unggah ulang 6 jam)' : '(penolakan kedua membatalkan pesanan)'}</p>
                                <select className={fieldClass} value={reason} onChange={(e) => setReason(e.target.value)}>
                                    {templates.map((t) => <option key={t}>{t}</option>)}
                                    <option value="lainnya">Lainnya…</option>
                                </select>
                                {reason === 'lainnya' && <input className={fieldClass} placeholder="Tulis alasan" value={custom} onChange={(e) => setCustom(e.target.value)} />}
                                <ConfirmButton
                                    variant="destructive"
                                    label={<><XCircle /> Tolak bukti</>}
                                    title="Tolak bukti transfer?"
                                    body={order.rejection_count === 0 ? 'Pembeli menerima email berisi alasan dan bisa mengunggah ulang.' : 'Ini penolakan kedua. Pesanan akan dibatalkan dan stok dikembalikan.'}
                                    onConfirm={() => router.post(`/panel/pembayaran/${order.code}/tolak`, { reason: reason === 'lainnya' ? custom : reason })}
                                />
                            </div>
                        </Card>
                    )}
                    {!canVerify && <p className="rounded-xl bg-muted p-4 text-sm">Hanya Admin Transaksi dan Super Admin yang bisa menyetujui atau menolak pembayaran.</p>}

                    <Card title="Pembeli">
                        <p className="font-semibold">{buyer.name}</p>
                        <p className="text-sm text-muted-foreground">{buyer.email} · {buyer.phone}</p>
                        <div className="mt-3 flex flex-wrap gap-2 text-xs">
                            <Pill tone={buyer.risk.level === 'tinggi' ? 'bad' : buyer.risk.level === 'sedang' ? 'warn' : 'good'}>Risiko {String(buyer.risk.level)}</Pill>
                            <Pill>{buyer.risk.expired} kedaluwarsa</Pill>
                            <Pill>{buyer.risk.rejected_proofs} bukti ditolak</Pill>
                            <Pill>{buyer.risk.duplicate_proofs} bukti ganda</Pill>
                        </div>
                        <ul className="mt-4 divide-y text-sm">
                            {buyer.orders.map((o) => (
                                <li key={o.code} className="flex justify-between gap-2 py-2"><span className="font-mono">{o.code}</span><OrderStatusPill status={o.status} /><span className="price">{rupiah(o.grand_total)}</span><span className="text-muted-foreground">{formatDate(o.created_at, 'id')}</span></li>
                            ))}
                            {buyer.orders.length === 0 && <li className="py-2 text-muted-foreground">Pesanan pertama pembeli ini.</li>}
                        </ul>
                    </Card>
                </div>
            </div>
        </PanelPage>
    );
}
