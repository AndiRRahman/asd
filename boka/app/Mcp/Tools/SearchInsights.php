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

#[Name('toko_search_insights')]
#[Description('Kata kunci pencarian populer, atau kata kunci tanpa hasil (barang yang dicari warga tetapi belum dijual). type: "popular" atau "no_results". Rentang tanggal WITA, bawaan 30 hari.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class SearchInsights extends TokoTool
{
    protected string $permission = 'laporan.lihat';

    protected function run(Request $request, User $user): Response|ResponseFactory
    {
        $data = $request->validate(['type' => ['nullable', 'in:popular,no_results'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
        [$from, $to] = $this->period($request);
        $noResults = ($data['type'] ?? 'no_results') === 'no_results';

        return Response::structured([
            'type' => $noResults ? 'no_results' : 'popular',
            'keywords' => app(Reports::class)->searchKeywords($from, $to, $noResults)->take(30)->values()->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->enum(['popular', 'no_results'])->description('Bawaan no_results.'),
            'from' => $schema->string()->description('Tanggal awal WITA (YYYY-MM-DD).'),
            'to' => $schema->string()->description('Tanggal akhir WITA (YYYY-MM-DD).'),
        ];
    }
}
