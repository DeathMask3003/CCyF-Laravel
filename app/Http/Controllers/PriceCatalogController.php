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

        $categories = DB::connection('legacy')->table('tm_categoria_widi')
            ->where('est', 1)->orderByDesc('cat_id')->get(['cat_id', 'cat_nom']);
        $configured = DB::table('ccyf_catalogos')
            ->whereIn('legacy_cat_id', $categories->pluck('cat_id'))
            ->get()->keyBy('legacy_cat_id');

        return view('catalogos.index', compact('categories', 'configured'));
    }

    public function show(int $category, Request $request, LegacyMenu $menu, PriceCatalogs $catalogs): View
    {
        $this->authorizeCatalog($request, $menu);
        $legacy = $catalogs->legacyCategory($category);
        abort_unless($legacy, 404);

        $catalog = DB::table('ccyf_catalogos')->where('legacy_cat_id', $category)->first();
        $products = $catalog ? DB::table('ccyf_productos')->where('catalogo_id', $catalog->id)
            ->orderBy('orden')->orderBy('id')->get() : collect();
        $suggestedType = $catalogs->suggestedType($category, (string) $legacy->cat_nom);

        return view('catalogos.show', compact('legacy', 'catalog', 'products', 'suggestedType'));
    }

    public function prepare(int $category, Request $request, LegacyMenu $menu, PriceCatalogs $catalogs): RedirectResponse
    {
        $this->authorizeCatalog($request, $menu);
        abort_unless($catalogs->legacyCategory($category, true), 404);
        $data = $request->validate(['tipo' => ['required', 'in:cafeteria,fotocopiado']]);
        $catalogs->prepare($category, $data['tipo']);

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

    private function catalog(int $category): object
    {
        $catalog = DB::table('ccyf_catalogos')->where('legacy_cat_id', $category)->first();
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
