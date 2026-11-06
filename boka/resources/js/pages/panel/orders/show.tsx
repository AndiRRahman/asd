import { router, useForm } from '@inertiajs/react';
import { CheckCircle2, MapPin, Printer, Store, Truck } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Card, ConfirmButton, OrderStatusPill, PanelPage, Pill, fieldClass } from '@/components/panel/ui';
import { Button } from '@/components/ui/button';
import { formatDate, grams, rupiah } from '@/lib/format';
import type { OrderDetail, Shipment } from '@/types/boka';

type Refund = { id: number; amount: number; bank_name: string; account_number: string; account_holder: string; reason: string; status: string; mine: boolean; refunded_at: string | null };
type Props = { order: OrderDetail; buyer: { name: string; email: string; phone: string }; refunds: Refund[]; canRefund: boolean };

function ShipmentActions({ shipment, orderStatus }: { shipment: Shipment; orderStatus: string }) {
    const tracking = useForm({ tracking_number: shipment.tracking_number ?? '' });
    const pickup = useForm({ code: '' });
    const delivered = useForm<{ photo: File | null; receiver_name: string }>({ photo: null, receiver_name: '' });

    if (orderStatus !== 'diproses' && orderStatus !== 'dikirim') {
        return null;
    }

    if (shipment.method === 'ekspedisi') {
        return orderStatus === 'diproses' && !shipment.tracking_number ? (
            <form className="flex flex-wrap gap-2" onSubmit={(e) => { e.preventDefault(); tracking.post(`/panel/paket/${shipment.id}/resi`, { preserveScroll: true }); }}>
                <input className={`${fieldClass} max-w-xs`} placeholder="Nomor resi" value={tracking.data.tracking_number} onChange={(e) => tracking.setData('tracking_number', e.target.value)} />
                <Button type="submit" disabled={tracking.processing}>Simpan resi</Button>
                <InputError message={tracking.errors.tracking_number} />
            </form>
        ) : null;
    }

    if (shipment.method === 'ambil') {
        if (shipment.status === 'menunggu') {
            return orderStatus === 'diproses' ? <Button onClick={() => router.post(`/panel/paket/${shipment.id}/siap-diambil`, {}, { preserveScroll: true })}>Tandai siap diambil</Button> : null;
        }

        return shipment.status === 'siap_diambil' ? (
            <form className="flex flex-wrap gap-2" onSubmit={(e) => { e.preventDefault(); pickup.post(`/panel/paket/${shipment.id}/kode-ambil`, { preserveScroll: true, onSuccess: () => pickup.reset() }); }}>
                <input className={`${fieldClass} max-w-40 font-mono tracking-widest`} inputMode="numeric" maxLength={6} placeholder="Kode 6 digit" value={pickup.data.code} onChange={(e) => pickup.setData('code', e.target.value.replace(/\D/g, ''))} />
                <Button type="submit" disabled={pickup.data.code.length !== 6}>Cocokkan kode</Button>
                <InputError message={pickup.errors.code} />
            </form>
        ) : null;
    }

    return shipment.status === 'menunggu' && orderStatus === 'diproses' ? (
        <form className="flex flex-wrap items-end gap-2" onSubmit={(e) => { e.preventDefault(); delivered.post(`/panel/paket/${shipment.id}/terkirim`, { forceFormData: true, preserveScroll: true }); }}>
            <label className="grid gap-1 text-xs">Foto serah terima<input type="file" accept="image/*" capture="environment" onChange={(e) => delivered.setData('photo', e.target.files?.[0] ?? null)} className="text-sm" /></label>
            <input className={`${fieldClass} max-w-xs`} placeholder="Nama penerima" value={delivered.data.receiver_name} onChange={(e) => delivered.setData('receiver_name', e.target.value)} />
            <Button type="submit" disabled={!delivered.data.photo || !delivered.data.receiver_name}>Tandai terkirim</Button>
            <InputError message={delivered.errors.photo ?? delivered.errors.receiver_name} />
        </form>
    ) : null;
}

export default function OrderShow({ order, buyer, refunds, canRefund }: Props) {
    const [cancelReason, setCancelReason] = useState('');
    const refund = useForm({ amount: order.grand_total, bank_name: '', account_number: '', account_holder: buyer.name, reason: '' });
    const approve = useForm<{ proof: File | null }>({ proof: null });

    return (
        <PanelPage
            title={`Pesanan ${order.code}`}
            description={`${buyer.name} · ${buyer.email} · ${buyer.phone}`}
            actions={<><OrderStatusPill status={order.status} /><Button variant="outline" asChild><a href={`/panel/pesanan/${order.code}/label`} target="_blank" rel="noopener"><Printer /> Cetak label</a></Button></>}
        >
            <div className="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
                <div className="space-y-4">
                    {order.shipments.map((s) => (
                        <Card key={s.id} title={`Paket ${s.number} dari ${s.origin.name}`} actions={<Pill tone={s.status === 'menunggu' ? 'warn' : 'good'}>{s.status.replace('_', ' ')}</Pill>}>
                            <p className="mb-3 flex items-center gap-2 text-sm text-muted-foreground">
                                {s.method === 'ambil' ? <Store className="size-4" /> : s.method === 'lokal' ? <MapPin className="size-4" /> : <Truck className="size-4" />}
                                {s.courier ?? 'Ambil sendiri'} {s.service} · {grams(s.weight_gram)} · {rupiah(s.cost)}
                                {s.tracking_number && <> · Resi <b className="font-mono text-foreground">{s.tracking_number}</b></>}
                                {s.receiver_name && <> · Diterima {s.receiver_name}</>}
                            </p>
                            <ul className="mb-4 divide-y rounded-lg border text-sm">
                                {order.items.filter((i) => i.shipment_id === s.id).map((i) => (
                                    <li key={i.id} className="flex justify-between gap-2 px-3 py-2"><span>{i.qty}× {i.product_name}{i.variant_name && ` · ${i.variant_name}`}</span><span className="price">{rupiah(i.unit_price * i.qty)}</span></li>
                                ))}
                            </ul>
                            <ShipmentActions shipment={s} orderStatus={order.status} />
                        </Card>
                    ))}

                    {order.status === 'menunggu_verifikasi' && (
                        <Card title="Verifikasi Pesanan" actions={<OrderStatusPill status={order.status} />}>
                            <p className="mb-4 text-sm text-muted-foreground">
                                Pesanan baru masuk dan menunggu verifikasi admin. Periksa item dan alamat kirim, lalu klik tombol di bawah untuk menyetujui dan memproses pesanan ini.
                            </p>
                            <div className="flex flex-wrap gap-2">
                                <Button onClick={() => router.post(`/panel/pesanan/${order.code}/verifikasi`)}>
                                    <CheckCircle2 className="size-4" /> Verifikasi & Proses Pesanan
                                </Button>
                            </div>
                        </Card>
                    )}

                    {(order.status === 'diproses' || order.status === 'menunggu_verifikasi' || order.status === 'menunggu_pembayaran') && (
                        <Card title="Batalkan pesanan">
                            <p className="mb-3 text-sm text-muted-foreground">
                                {order.status === 'menunggu_verifikasi'
                                    ? 'Jika pesanan tidak valid atau dibatalkan, Anda dapat membatalkan pesanan ini.'
                                    : 'Fase 1: jika satu titik asal tidak bisa mengirim, batalkan seluruh pesanan lalu catat pengembalian dana di bawah.'}
                            </p>
                            <div className="flex flex-wrap gap-2">
                                <input className={`${fieldClass} max-w-md`} placeholder="Alasan pembatalan" value={cancelReason} onChange={(e) => setCancelReason(e.target.value)} />
                                <ConfirmButton variant="destructive" disabled={!cancelReason} label="Batalkan" title="Batalkan pesanan ini?" body="Stok dan voucher dikembalikan. Aksi ini tidak bisa dibatalkan." onConfirm={() => router.post(`/panel/pesanan/${order.code}/batal`, { reason: cancelReason })} />
                            </div>
                        </Card>
                    )}

                    {canRefund && (
                        <Card title="Pengembalian dana (refund)">
                            <ul className="mb-4 space-y-3 text-sm">
                                {refunds.map((r) => (
                                    <li key={r.id} className="rounded-lg border p-3">
                                        <div className="flex justify-between gap-2"><b className="price">{rupiah(r.amount)}</b><Pill tone={r.status === 'selesai' ? 'good' : 'warn'}>{r.status}</Pill></div>
                                        <p className="text-muted-foreground">{r.bank_name} {r.account_number} a.n. {r.account_holder} · {r.reason}</p>
                                        {r.status === 'diajukan' && (r.mine ? (
                                            <p className="mt-2 text-xs">Menunggu persetujuan Admin Transaksi lain.</p>
                                        ) : (
                                            <form className="mt-2 flex flex-wrap items-center gap-2" onSubmit={(e) => { e.preventDefault(); approve.post(`/panel/refund/${r.id}/setujui`, { forceFormData: true, preserveScroll: true }); }}>
                                                <label className="text-xs">Bukti transfer refund <input type="file" accept="image/*,application/pdf" onChange={(e) => approve.setData('proof', e.target.files?.[0] ?? null)} /></label>
                                                <Button type="submit" size="sm" disabled={!approve.data.proof}>Setujui & tandai selesai</Button>
                                            </form>
                                        ))}
                                    </li>
                                ))}
                            </ul>
                            {(order.status === 'dibatalkan' || order.status === 'diproses' || order.status === 'selesai') && (
                                <form className="grid gap-3 sm:grid-cols-2" onSubmit={(e) => { e.preventDefault(); refund.post(`/panel/pesanan/${order.code}/refund`, { preserveScroll: true }); }}>
                                    <input className={fieldClass} type="number" placeholder="Nominal" value={refund.data.amount} onChange={(e) => refund.setData('amount', Number(e.target.value))} />
                                    <input className={fieldClass} placeholder="Bank" value={refund.data.bank_name} onChange={(e) => refund.setData('bank_name', e.target.value)} />
                                    <input className={fieldClass} placeholder="Nomor rekening" value={refund.data.account_number} onChange={(e) => refund.setData('account_number', e.target.value)} />
                                    <input className={fieldClass} placeholder="Atas nama (harus Pembeli/pengirim)" value={refund.data.account_holder} onChange={(e) => refund.setData('account_holder', e.target.value)} />
                                    <input className={`${fieldClass} sm:col-span-2`} placeholder="Alasan" value={refund.data.reason} onChange={(e) => refund.setData('reason', e.target.value)} />
                                    <InputError message={Object.values(refund.errors)[0]} />
                                    <Button type="submit" className="sm:col-span-2" disabled={refund.processing}>Ajukan refund</Button>
                                </form>
                            )}
                        </Card>
                    )}
                </div>

                <div className="space-y-4">
                    <Card title="Rincian biaya">
                        <dl className="space-y-1.5 text-sm">
                            <div className="flex justify-between"><dt>Subtotal</dt><dd className="price">{rupiah(order.subtotal)}</dd></div>
                            <div className="flex justify-between"><dt>Voucher belanja</dt><dd className="price">−{rupiah(order.voucher_discount)}</dd></div>
                            <div className="flex justify-between"><dt>Ongkir</dt><dd className="price">{rupiah(order.shipping_cost)}</dd></div>
                            <div className="flex justify-between"><dt>Voucher ongkir</dt><dd className="price">−{rupiah(order.shipping_discount)}</dd></div>
                            <div className="flex justify-between"><dt>Kode unik</dt><dd className="price">{rupiah(order.unique_code)}</dd></div>
                            <div className="flex justify-between border-t pt-2 font-extrabold"><dt>Total</dt><dd className="price">{rupiah(order.grand_total)}</dd></div>
                        </dl>
                    </Card>
                    <Card title="Alamat kirim (snapshot)">
                        <p className="text-sm">{order.address.recipient} · {order.address.phone}<br />{order.address.full_address}<br />{order.address.destination_label} {order.address.postal_code}</p>
                    </Card>
                    <Card title="Riwayat status">
                        <ol className="space-y-3 text-sm">
                            {order.histories.map((h, i) => (
                                <li key={i}><OrderStatusPill status={h.to} /> <span className="text-muted-foreground">{formatDate(h.at, 'id', true)}</span>{h.note && <p className="mt-0.5">{h.note}</p>}</li>
                            ))}
                        </ol>
                    </Card>
                </div>
            </div>
        </PanelPage>
    );
}
