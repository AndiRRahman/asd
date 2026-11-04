<?php

namespace App\Mcp\Tools;

use App\Models\Order;
use App\Models\User;
use App\Support\Present;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('toko_get_order')]
#[Description('Detail satu pesanan berdasarkan kode (contoh BN-261102-7K3Q9): status, item, paket per titik asal beserta resi, rincian biaya, riwayat status, dan metadata bukti transfer (tanpa file). Data pribadi pembeli disamarkan kecuali token milik Admin Transaksi.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class GetOrder extends TokoTool
{
    protected string $permission = 'pesanan.kelola';

    protected function run(Request $request, User $user): Response|ResponseFactory
    {
        $code = strtoupper(trim((string) $request->get('code')));
        $order = Order::query()->where('code', $code)->with('user')->first();

        if (! $order) {
            return Response::error("Pesanan {$code} tidak ditemukan. Periksa format kode (BN-YYMMDD-XXXXX) atau cari dulu dengan toko_search_orders.");
        }

        $detail = Present::orderDetail($order, forBuyer: false);
        $personal = $user->can('ekspor.data_pribadi');

        return Response::structured([
            ...collect($detail)->except(['bank', 'can_upload_proof', 'can_cancel', 'can_complete', 'can_review'])->all(),
            'buyer' => [
                'name' => $personal ? $order->user->name : Present::maskName($order->user->name),
                'phone' => $personal ? $order->user->phone : Present::maskPhone($order->user->phone),
            ],
            'address' => ['district' => $order->address_snapshot['district'] ?? null] + ($personal ? $order->address_snapshot : []),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return ['code' => $schema->string()->description('Kode pesanan, contoh BN-261102-7K3Q9.')->required()];
    }
}
