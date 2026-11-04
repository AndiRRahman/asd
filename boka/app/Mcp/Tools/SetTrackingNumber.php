<?php

namespace App\Mcp\Tools;

use App\Domain\BusinessRuleException;
use App\Domain\Order\OrderService;
use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Name('toko_set_tracking_number')]
#[Description('Isi nomor resi untuk satu paket pesanan berstatus Diproses. Pesanan dengan beberapa paket butuh package_number (1, 2, ...); lihat daftar paket dengan toko_get_order. Mengirim nomor yang sama dua kali tidak mengubah apa pun. Status pesanan otomatis menjadi Dikirim setelah semua paket punya resi.')]
#[IsDestructive(false)]
#[IsIdempotent]
#[IsOpenWorld(false)]
class SetTrackingNumber extends TokoTool
{
    protected string $permission = 'pesanan.kelola';

    protected function run(Request $request, User $user): Response|ResponseFactory
    {
        $data = $request->validate(['code' => ['required', 'string'], 'tracking_number' => ['required', 'string', 'max:60'], 'package_number' => ['nullable', 'integer', 'min:1']]);
        $order = Order::query()->where('code', strtoupper($data['code']))->with('shipments')->first();

        if (! $order) {
            return Response::error("Pesanan {$data['code']} tidak ditemukan. Cari dengan toko_search_orders status=diproses.");
        }

        $shipments = $order->shipments->where('method', 'ekspedisi')->values();

        if ($shipments->count() > 1 && empty($data['package_number'])) {
            return Response::error("Pesanan ini punya {$shipments->count()} paket ekspedisi. Isi package_number (1 sampai {$shipments->count()}).");
        }

        $shipment = $order->shipments->values()->get(($data['package_number'] ?? 1) - 1);

        if (! $shipment || $shipment->method !== 'ekspedisi') {
            return Response::error('Paket tersebut bukan paket ekspedisi. Paket kurir lokal dan ambil sendiri diproses di panel.');
        }

        if ($shipment->tracking_number === trim($data['tracking_number'])) {
            return Response::structured(['code' => $order->code, 'package_number' => $data['package_number'] ?? 1, 'tracking_number' => $shipment->tracking_number, 'changed' => false, 'order_status' => $order->status->value]);
        }

        try {
            app(OrderService::class)->setTracking($shipment, $data['tracking_number'], $user);
        } catch (BusinessRuleException $e) {
            return Response::error($e->getMessage());
        }

        return Response::structured(['code' => $order->code, 'package_number' => $data['package_number'] ?? 1, 'tracking_number' => trim($data['tracking_number']), 'changed' => true, 'order_status' => $order->refresh()->status->value]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'code' => $schema->string()->description('Kode pesanan.')->required(),
            'tracking_number' => $schema->string()->description('Nomor resi dari kurir.')->required(),
            'package_number' => $schema->integer()->description('Nomor paket (mulai 1). Wajib jika pesanan punya lebih dari satu paket.'),
        ];
    }
}
