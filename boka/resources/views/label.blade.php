<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Label {{ $order->code }}</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 16px; color: #1F1B16; }
        .label { width: 100mm; border: 2px dashed #1F1B16; padding: 12px; margin-bottom: 16px; page-break-inside: avoid; }
        h2 { margin: 0 0 6px; font-size: 16px; }
        .small { font-size: 12px; color: #444; }
        .big { font-size: 15px; font-weight: 700; }
        @media print { button { display: none; } }
    </style>
</head>
<body>
<button type="button" id="cetak">Cetak</button>
<script nonce="{{ Vite::cspNonce() }}">document.getElementById('cetak').addEventListener('click', () => window.print());</script>
@foreach ($order->shipments as $i => $shipment)
    <div class="label">
        <h2>BOKA · {{ $order->code }} · Paket {{ $i + 1 }}/{{ $order->shipments->count() }}</h2>
        <div class="small">Metode: {{ $shipment->method }} {{ $shipment->courier }} {{ $shipment->service }} · Berat {{ number_format($shipment->weight_gram / 1000, 2, ',', '.') }} kg</div>
        <hr>
        <div class="small">Kepada:</div>
        <div class="big">{{ $order->address_snapshot['recipient'] }} ({{ $order->address_snapshot['phone'] }})</div>
        <div>{{ $order->address_snapshot['full_address'] }}</div>
        <div>{{ $order->address_snapshot['destination_label'] }} {{ $order->address_snapshot['postal_code'] ?? '' }}</div>
        <hr>
        <div class="small">Dari: {{ $shipment->origin_snapshot['name'] }}, {{ $shipment->origin_snapshot['address'] }} ({{ $shipment->origin_snapshot['contact'] ?? '' }})</div>
        <div class="small">Isi: {{ $shipment->items->map(fn ($i) => $i->qty.'× '.$i->product_name)->implode(', ') }}</div>
    </div>
@endforeach
</body>
</html>
