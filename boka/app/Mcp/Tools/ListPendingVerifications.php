<?php

namespace App\Mcp\Tools;

use App\Domain\Order\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('toko_list_pending_verifications')]
#[Description('Daftar pesanan yang menunggu verifikasi pembayaran, diurutkan dari yang paling lama menunggu. Mengembalikan kode pesanan, nama pembeli, nominal tagihan (termasuk kode unik), umur antrean dalam jam, dan tahap (menunggu / sudah persetujuan pertama). Pakai min_age_hours untuk menjawab "mana yang menunggu lebih dari 12 jam".')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class ListPendingVerifications extends TokoTool
{
    protected string $permission = 'pesanan.kelola';

    protected function run(Request $request, User $user): Response|ResponseFactory
    {
        $data = $request->validate(['min_age_hours' => ['nullable', 'numeric', 'min:0'], 'limit' => ['nullable', 'integer', 'between:1,50']]);

        $orders = Order::query()->where('status', OrderStatus::MenungguVerifikasi)->with(['user:id,name', 'latestProof'])->get()
            ->map(fn (Order $o) => [
                'code' => $o->code,
                'buyer' => $o->user->name,
                'grand_total' => $o->grand_total,
                'grand_total_text' => self::rupiah($o->grand_total),
                'waiting_hours' => round(($o->latestProof?->created_at ?? $o->updated_at)->diffInMinutes(now()) / 60, 1),
                'stage' => $o->latestProof?->status === 'disetujui_1' ? 'menunggu_persetujuan_kedua' : 'menunggu_verifikasi',
            ])
            ->filter(fn ($o) => $o['waiting_hours'] >= (float) ($data['min_age_hours'] ?? 0))
            ->sortByDesc('waiting_hours')->values();

        return Response::structured([
            'total' => $orders->count(),
            'orders' => $orders->take($data['limit'] ?? 20)->all(),
            'note' => 'Menyetujui atau menolak pembayaran sengaja tidak tersedia lewat MCP. Lakukan di panel /panel/pembayaran.',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'min_age_hours' => $schema->number()->description('Hanya pesanan yang menunggu minimal sekian jam. Contoh: 12.'),
            'limit' => $schema->integer()->description('Jumlah maksimal hasil, 1 sampai 50. Bawaan 20.'),
        ];
    }
}
