<?php

namespace App\Http\Controllers\Shop;

use App\Domain\Search\SearchService;
use App\Http\Controllers\Controller;
use App\Support\HashId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function suggest(Request $request, SearchService $search): JsonResponse
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        return response()->json($search->suggest($data['q'] ?? '', $request->user()?->id, $request->session()->getId()));
    }

    public function log(Request $request, SearchService $search): JsonResponse
    {
        $request->merge(['product_id' => HashId::decode($request->input('product_id'))]);
        $data = $request->validate([
            'q' => ['required', 'string', 'max:100'],
            'results' => ['required', 'integer', 'min:0'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
        ]);

        $search->log($data['q'], $data['results'], $request->user()?->id, $request->session()->getId(), $data['product_id'] ?? null);
        $request->session()->put('searched', true);

        return response()->json(['ok' => true]);
    }
}
