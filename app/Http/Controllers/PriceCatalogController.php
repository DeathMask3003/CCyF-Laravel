<?php

namespace App\Http\Controllers;

use App\Services\LegacyMenu;
use App\Services\PriceCatalogs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PriceCatalogController extends Controller
{
    public function index(Request $request, LegacyMenu $menu, PriceCatalogs $catalogs): View
    {
        $this->authorizeCatalog($request, $menu);

        $categories = DB::table('ccyf_convocatorias')->where('activo', true)->orderByDesc('id')
            ->get(['id as cat_id', 'numero as cat_nom']);
        $configured = DB::table('ccyf_catalogos')
            ->leftJoin('ccyf_tipos_servicio as servicio', 'servicio.id', '=', 'ccyf_catalogos.servicio_id')
            ->whereIn('convocatoria_id', $categories->pluck('cat_id'))
            ->get(['ccyf_catalogos.*', 'servicio.nombre as servicio_nombre'])->keyBy('convocatoria_id');

        return view('catalogos.index', compact('categories', 'configured'));
    }

    public function show(int $category, Request $request, LegacyMenu $menu, PriceCatalogs $catalogs): View
    {
        $this->authorizeCatalog($request, $menu);
        $legacy = $catalogs->category($category);
        abort_unless($legacy, 404);

        $catalog = DB::table('ccyf_catalogos')
            ->leftJoin('ccyf_tipos_servicio as servicio', 'servicio.id', '=', 'ccyf_catalogos.servicio_id')
            ->where('convocatoria_id', $category)
            ->first(['ccyf_catalogos.*', 'servicio.nombre as servicio_nombre']);
        $products = $catalog ? DB::table('ccyf_productos')->where('catalogo_id', $catalog->id)
            ->orderBy('orden')->orderBy('id')->get() : collect();
        $catalogVersion = $catalog ? $this->catalogVersion($products) : null;
        $service = DB::table('ccyf_tipos_servicio')->where('id', $legacy->servicio_id)->first();

        return view('catalogos.show', compact('legacy', 'catalog', 'products', 'service', 'catalogVersion'));
    }

    public function prepare(int $category, Request $request, LegacyMenu $menu, PriceCatalogs $catalogs): RedirectResponse
    {
        $this->authorizeCatalog($request, $menu);
        abort_unless($catalogs->category($category, true), 404);
        $catalogs->prepare($category);

        return redirect()->route('catalogos.show', $category)->with('status', 'Catálogo preparado. Puedes ajustar sus productos.');
    }

    public function storeProduct(int $category, Request $request, LegacyMenu $menu): RedirectResponse
    {
        $this->authorizeCatalog($request, $menu);
        $catalog = $this->catalog($category);
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'unidad' => ['nullable', 'string', 'max:40'],
        ]);
        $this->assertUniqueName($catalog->id, trim($data['nombre']));
        $nextOrder = (int) DB::table('ccyf_productos')->where('catalogo_id', $catalog->id)->max('orden') + 1;
        DB::table('ccyf_productos')->insert([
            'catalogo_id' => $catalog->id,
            'nombre' => trim($data['nombre']),
            'unidad' => trim($data['unidad'] ?? '') ?: null,
            'orden' => $nextOrder,
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('status', 'Producto agregado a esta convocatoria.');
    }

    public function updateProduct(int $category, int $product, Request $request, LegacyMenu $menu): RedirectResponse
    {
        $this->authorizeCatalog($request, $menu);
        $catalog = $this->catalog($category);
        $item = DB::table('ccyf_productos')->where('catalogo_id', $catalog->id)->where('id', $product)->first();
        abort_unless($item, 404);
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'unidad' => ['nullable', 'string', 'max:40'],
            'orden' => ['required', 'integer', 'between:1,999'],
            'activo' => ['nullable', 'boolean'],
        ]);
        $this->assertUniqueName($catalog->id, trim($data['nombre']), $product);
        DB::table('ccyf_productos')->where('id', $product)->update([
            'nombre' => trim($data['nombre']),
            'unidad' => trim($data['unidad'] ?? '') ?: null,
            'orden' => (int) $data['orden'],
            'activo' => (bool) ($data['activo'] ?? false),
            'updated_at' => now(),
        ]);

        return back()->with('status', 'Producto actualizado.');
    }

    public function updateProducts(int $category, Request $request, LegacyMenu $menu): RedirectResponse
    {
        $this->authorizeCatalog($request, $menu);
        $catalog = $this->catalog($category);
        $data = $request->validate([
            'version' => ['required', 'string', 'size:64'],
            'products' => ['required', 'array', 'min:1'],
            'products.*.nombre' => ['required', 'string', 'max:120'],
            'products.*.unidad' => ['nullable', 'string', 'max:40'],
            'products.*.orden' => ['required', 'integer', 'between:1,999'],
            'products.*.activo' => ['required', 'boolean'],
        ]);

        $changed = DB::transaction(function () use ($catalog, $data): int {
            $products = DB::table('ccyf_productos')->where('catalogo_id', $catalog->id)
                ->orderBy('id')->lockForUpdate()->get();
            abort_unless(hash_equals($this->catalogVersion($products), $data['version']), 409,
                'El catálogo cambió desde que abriste la página. Actualízala antes de guardar.');
            $expected = $products->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            $received = collect(array_keys($data['products']))->map(fn ($id) => (int) $id)->sort()->values()->all();
            abort_unless($expected === $received, 422,
                'La lista de productos está incompleta. Actualiza la página antes de guardar.');

            $names = collect($data['products'])->map(fn ($row) => mb_strtolower(trim($row['nombre'])));
            if ($names->unique()->count() !== $products->count()) {
                throw ValidationException::withMessages(['products' => 'Hay productos con el mismo nombre en esta convocatoria.']);
            }

            $changed = 0;
            foreach ($products as $product) {
                $row = $data['products'][$product->id];
                $values = [
                    'nombre' => trim($row['nombre']),
                    'unidad' => trim($row['unidad'] ?? '') ?: null,
                    'orden' => (int) $row['orden'],
                    'activo' => (bool) $row['activo'],
                ];
                if ($product->nombre === $values['nombre'] && $product->unidad === $values['unidad']
                    && (int) $product->orden === $values['orden'] && (bool) $product->activo === $values['activo']) {
                    continue;
                }
                DB::table('ccyf_productos')->where('id', $product->id)->update($values + ['updated_at' => now()]);
                $changed++;
            }
            return $changed;
        });

        return redirect()->route('catalogos.show', $category)
            ->with('status', $changed ? "Se guardaron {$changed} productos." : 'No había cambios pendientes.');
    }

    private function catalogVersion($products): string
    {
        return hash('sha256', $products->sortBy('id')->values()->map(fn ($product) => [
            (int) $product->id, $product->nombre, $product->unidad,
            (int) $product->orden, (bool) $product->activo,
        ])->toJson());
    }

    private function catalog(int $category): object
    {
        $catalog = DB::table('ccyf_catalogos')->where('convocatoria_id', $category)->first();
        abort_unless($catalog, 404);

        return $catalog;
    }

    private function authorizeCatalog(Request $request, LegacyMenu $menu): void
    {
        abort_unless($menu->allows($request->user(), 'Categorias_widi'), 403);
    }

    private function assertUniqueName(int $catalog, string $name, ?int $except = null): void
    {
        $names = DB::table('ccyf_productos')->where('catalogo_id', $catalog)
            ->when($except, fn ($query) => $query->where('id', '!=', $except))
            ->pluck('nombre');

        if ($names->contains(fn ($existing) => mb_strtolower(trim($existing)) === mb_strtolower($name))) {
            throw ValidationException::withMessages(['nombre' => 'Ya existe un producto con ese nombre en esta convocatoria.']);
        }
    }
}
