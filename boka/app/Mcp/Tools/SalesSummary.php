<?php

namespace App\Mcp\Tools;

use App\Models\User;
use App\Support\Reports;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('toko_sales_summary')]
#[Description('Ringkasan penjualan dari pesanan yang sudah dibayar (Diproses, Dikirim, Selesai) dalam rentang tanggal WITA. group_by: "day" (omzet per hari), "producer" (per Karang Taruna/UMKM), atau "product" (produk terlaris). Angka sama dengan laporan di panel.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class SalesSummary extends TokoTool
{
    protected string $permission = 'laporan.lihat';

    protected function run(Request $request, User $user): Response|ResponseFactory
    {
        $data = $request->validate(['group_by' => ['nullable', 'in:day,producer,product'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
        [$from, $to] = $this->period($request);
        $reports = app(Reports::class);

        $rows = match ($data['group_by'] ?? 'producer') {
            'day' => $reports->salesByDay($from, $to),
            'product' => $reports->topProducts($from, $to, 20),
            default => $reports->salesByProducer($from, $to),
        };

        return Response::structured([
            'from' => $from->timezone('Asia/Makassar')->toDateString(),
            'to' => $to->timezone('Asia/Makassar')->toDateString(),
            'total_revenue' => $reports->revenue($from, $to),
            'group_by' => $data['group_by'] ?? 'producer',
            'rows' => $rows->values()->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'group_by' => $schema->string()->enum(['day', 'producer', 'product'])->description('Pengelompokan. Bawaan producer.'),
            'from' => $schema->string()->description('Tanggal awal WITA (YYYY-MM-DD).'),
            'to' => $schema->string()->description('Tanggal akhir WITA (YYYY-MM-DD).'),
        ];
    }
}
