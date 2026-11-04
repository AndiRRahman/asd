@php($rp = fn ($n) => 'Rp '.number_format($n, 0, ',', '.'))
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice {{ $order->code }} · Boka</title>
    <style>
        :root { --ink: #14201A; --brand: #2F6B4F; --line: #D9E3DB; }
        * { box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; color: var(--ink); margin: 0; padding: 32px; background: #F6F8F3; }
        .sheet { max-width: 760px; margin: 0 auto; background: #fff; padding: 40px; border: 1px solid var(--line); border-radius: 12px; }
        header { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; border-bottom: 2px solid var(--brand); padding-bottom: 20px; }
        h1 { margin: 0; color: var(--brand); font-size: 28px; letter-spacing: -.02em; }
        .muted { color: #6B6257; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; margin-top: 24px; font-size: 14px; }
        th, td { padding: 10px 8px; border-bottom: 1px solid var(--line); text-align: left; }
        td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
        .total td { font-weight: 700; font-size: 16px; border-bottom: none; }
        .qr { text-align: center; font-size: 11px; color: #6B6257; }
        .qr svg { width: 120px; height: 120px; }
        .actions { max-width: 760px; margin: 0 auto 16px; text-align: right; }
        button { background: var(--brand); color: #fff; border: 0; padding: 10px 18px; border-radius: 8px; font-weight: 600; cursor: pointer; }
        @media print { body { background: #fff; padding: 0; } .sheet { border: 0; } .actions { display: none; } }
    </style>
</head>
<body>
<div class="actions"><button type="button" id="cetak">Cetak / simpan PDF</button></div>
<script nonce="{{ Vite::cspNonce() }}">document.getElementById('cetak').addEventListener('click', () => window.print());</script>
<div class="sheet">
    <header>
        <div>
            <h1>Boka</h1>
            <div class="muted">Bone Karya · Karang Taruna & UMKM Kabupaten Bone</div>
            <div class="muted">{{ config('boka.store.address') }}</div>
        </div>
        <div class="qr">{!! $qr !!}<div>Pindai untuk verifikasi</div></div>
    </header>

    <p><strong>Invoice {{ $order->code }}</strong><br>
        <span class="muted">Tanggal {{ $order->created_at->timezone('Asia/Makassar')->format('d M Y H:i') }} WITA · Status {{ str_replace('_', ' ', $order->status->value) }}</span></p>

    <p class="muted">Dikirim ke: {{ $order->address_snapshot['recipient'] }}, {{ $order->address_snapshot['full_address'] }}, {{ $order->address_snapshot['destination_label'] }}</p>

    <table>
        <thead><tr><th>Produk</th><th class="num">Jumlah</th><th class="num">Harga</th><th class="num">Subtotal</th></tr></thead>
        <tbody>
        @foreach ($order->items as $item)
            <tr>
                <td>{{ $item->product_name }}@if ($item->variant_name) <span class="muted">· {{ $item->variant_name }}</span>@endif</td>
                <td class="num">{{ $item->qty }}</td>
                <td class="num">{{ $rp($item->unit_price) }}</td>
                <td class="num">{{ $rp($item->unit_price * $item->qty) }}</td>
            </tr>
        @endforeach
        <tr><td colspan="3">Subtotal</td><td class="num">{{ $rp($order->subtotal) }}</td></tr>
        @if ($order->voucher_discount)<tr><td colspan="3">Voucher belanja</td><td class="num">−{{ $rp($order->voucher_discount) }}</td></tr>@endif
        <tr><td colspan="3">Ongkos kirim ({{ $order->shipments->count() }} paket)</td><td class="num">{{ $rp($order->shipping_cost) }}</td></tr>
        @if ($order->shipping_discount)<tr><td colspan="3">Voucher ongkir</td><td class="num">−{{ $rp($order->shipping_discount) }}</td></tr>@endif
        @if ($order->service_fee)<tr><td colspan="3">Biaya layanan</td><td class="num">{{ $rp($order->service_fee) }}</td></tr>@endif
        <tr><td colspan="3">Kode unik transfer</td><td class="num">{{ $rp($order->unique_code) }}</td></tr>
        <tr class="total"><td colspan="3">Total</td><td class="num">{{ $rp($order->grand_total) }}</td></tr>
        </tbody>
    </table>

    <p class="muted" style="margin-top:24px">Boka tidak pernah meminta transfer ke rekening selain yang tertera di halaman pesanan dan halaman Rekening Resmi.</p>
</div>
</body>
</html>
