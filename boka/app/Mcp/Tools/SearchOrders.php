<?php

namespace App\Mcp\Tools;

use App\Domain\Order\OrderStatus;
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

#[Name('toko_search_orders')]
#[Description('Cari pesanan per status, rentang tanggal (WITA, format YYYY-MM-DD), atau nama pembeli. Berhalaman: gunakan next_page dari hasil sebelumnya. Status: menunggu_pembayaran, menunggu_verifikasi, diproses, dikirim, selesai, kedaluwarsa, dibatalkan.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class SearchOrders extends TokoTool
{
    protected string $permission = 'pesanan.kelola';

    protected function run(Request $request, User $user): Response|ResponseFactory
    {
        $data = $request->validate([
            'status' => ['nullable', 'in:'.implode(',', array_column(OrderStatus::cases(), 'value'))],
            'buyer_name' => ['nullable', 'string', 'max:120'],
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'between:1,50'],
        ]);
        [$from, $to] = $this->period($request);

        $page = Order::query()->with(['user:id,name', 'items'])
            ->when($data['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($data['buyer_name'] ?? null, fn ($q, $n) => $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$n}%")))
            ->whereBetween('created_at', [$from, $to])
            ->latest('id')
            ->paginate($data['per_page'] ?? 20, page: $data['page'] ?? 1);

        $personal = $user->can('ekspor.data_pribadi');

        return Response::structured([
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'next_page' => $page->hasMorePages() ? $page->currentPage() + 1 : null,
            'orders' => collect($page->items())->map(fn ($o) => [
                ...Present::orderSummary($o),
                'buyer' => $personal ? $o->user->name : Present::maskName($o->user->name),
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->description('Status pesanan.'),
            'buyer_name' => $schema->string()->description('Sebagian nama pembeli.'),
            'from' => $schema->string()->description('Tanggal awal WITA (YYYY-MM-DD). Bawaan 30 hari lalu.'),
            'to' => $schema->string()->description('Tanggal akhir WITA (YYYY-MM-DD). Bawaan hari ini.'),
            'page' => $schema->integer()->description('Halaman, mulai 1.'),
            'per_page' => $schema->integer()->description('1 sampai 50, bawaan 20.'),
        ];
    }
}
