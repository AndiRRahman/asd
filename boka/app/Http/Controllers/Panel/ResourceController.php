<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Panel\Resources;
use App\Support\HashId;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Satu controller untuk modul panel sederhana (lihat App\Panel\Resources).
 */
class ResourceController extends Controller
{
    public function index(Request $request, string $resource): Response
    {
        $class = $this->resolve($request, $resource);
        $search = $request->query('q');
        $rows = $class::query()
            ->when($search, fn ($q) => $q->where($class::columns()[0]['key'], 'like', "%{$search}%"))
            ->paginate(20)->withQueryString();

        return Inertia::render('panel/resource/index', [
            'resource' => $class::meta(),
            'rows' => [
                'data' => $rows->getCollection()->map(fn ($m) => [...$class::row($m), '_deletable' => $class::deletable($m)]),
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'total' => $rows->total(),
            ],
            'q' => $search,
        ]);
    }

    public function create(Request $request, string $resource): Response
    {
        $class = $this->resolve($request, $resource);

        return Inertia::render('panel/resource/form', ['resource' => $class::meta(), 'values' => null, 'id' => null]);
    }

    public function edit(Request $request, string $resource, string $id): Response
    {
        $class = $this->resolve($request, $resource);

        return Inertia::render('panel/resource/form', ['resource' => $class::meta(), 'values' => $class::toForm($class::$model::query()->findOrFail(HashId::decodeOrFail($id))), 'id' => $id]);
    }

    public function store(Request $request, string $resource): RedirectResponse
    {
        return $this->save($request, $this->resolve($request, $resource), null);
    }

    public function update(Request $request, string $resource, string $id): RedirectResponse
    {
        $class = $this->resolve($request, $resource);

        return $this->save($request, $class, $class::$model::query()->findOrFail(HashId::decodeOrFail($id)));
    }

    public function destroy(Request $request, string $resource, string $id): RedirectResponse
    {
        $class = $this->resolve($request, $resource);
        $model = $class::$model::query()->findOrFail(HashId::decodeOrFail($id));
        abort_unless($class::deletable($model), 409, 'Data ini sudah dipakai sehingga tidak bisa dihapus.');
        $model->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Data dihapus.']);

        return redirect("/panel/kelola/{$resource}");
    }

    /** @param  class-string<\App\Panel\Resource>  $class */
    private function save(Request $request, string $class, $model): RedirectResponse
    {
        $data = $request->validate($class::rules($model));
        $model ??= new $class::$model;

        DB::transaction(function () use ($class, $model, $data, $request) {
            $class::fill($model, $data);
            $model->save();
            $class::afterSave($model, $data, $request);
        });

        Cache::forget('nav-categories:id');
        Cache::forget('nav-categories:en');
        Inertia::flash('toast', ['type' => 'success', 'message' => $class::title().' tersimpan.']);

        return redirect("/panel/kelola/{$class::slug()}");
    }

    /** @return class-string<\App\Panel\Resource> */
    private function resolve(Request $request, string $resource): string
    {
        $class = Resources::all()[$resource] ?? abort(404);
        abort_unless($request->user()->can($class::$permission), 403);

        return $class;
    }
}
