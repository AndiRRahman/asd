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

#[Name('toko_low_stock')]
#[Description('Varian dengan stok di bawah atau sama dengan ambang stok menipis, termasuk yang habis (stok 0). Bisa difilter per nama titik asal kirim, misalnya "Watampone".')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class LowStock extends TokoTool
{
    protected string $permission = 'stok.kelola';

    protected function run(Request $request, User $user): Response|ResponseFactory
    {
        $origin = $request->get('origin');
        $rows = app(Reports::class)->lowStock()
            ->when($origin, fn ($c) => $c->filter(fn ($r) => str_contains(strtolower((string) $r['origin']), strtolower($origin))))
            ->values();

        return Response::structured(['total' => $rows->count(), 'out_of_stock' => $rows->where('stock', 0)->count(), 'variants' => $rows->all()]);
    }

    public function schema(JsonSchema $schema): array
    {
        return ['origin' => $schema->string()->description('Sebagian nama titik asal kirim, contoh "Bajoe".')];
    }
}
