<?php

namespace App\Http\Controllers;

use App\Services\LegacyMenu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NewOfficeController extends Controller
{
    public function index(Request $request, LegacyMenu $menu): View
    {
        $this->authorizeView($request, $menu);
        $categories = DB::connection('legacy')->table('tm_categoria_widi')
            ->where('est', 1)->get(['cat_id', 'cat_nom'])->keyBy('cat_id');
        $catalogs = DB::table('ccyf_catalogos')->whereIn('legacy_cat_id', $categories->keys())
            ->orderByDesc('legacy_cat_id')->get();
        $canSave = $menu->allows($request->user(), 'NuevoOficio');

        return view('oficios.index', compact('categories', 'catalogs', 'canSave'));
    }

    public function show(int $category, Request $request, LegacyMenu $menu): View
    {
        $this->authorizeView($request, $menu);
        $legacy = DB::connection('legacy')->table('tm_categoria_widi')
            ->where('cat_id', $category)->where('est', 1)->first(['cat_id', 'cat_nom']);
        abort_unless($legacy, 404);
        $catalog = DB::table('ccyf_catalogos')->where('legacy_cat_id', $category)->first();
        abort_unless($catalog, 404);
        $products = DB::table('ccyf_productos')->where('catalogo_id', $catalog->id)
            ->where('activo', true)->orderBy('orden')->orderBy('id')->get();
        $draft = DB::table('ccyf_precio_borradores')->where('catalogo_id', $catalog->id)
            ->where('usu_id', $request->user()->getKey())->first();
        $saved = $draft ? DB::table('ccyf_precio_borrador_detalles')
            ->where('borrador_id', $draft->id)->pluck('precio', 'producto_id') : collect();
        $documentTypes = DB::table('ccyf_tipos_documento')->where('activo', true)
            ->orderBy('nombre')->get(['id', 'nombre']);
        $canSave = $menu->allows($request->user(), 'NuevoOficio');

        return view('oficios.show', compact('legacy', 'catalog', 'products', 'saved', 'draft', 'documentTypes', 'canSave'));
    }

    public function save(int $category, Request $request, LegacyMenu $menu): RedirectResponse
    {
        abort_unless($menu->allows($request->user(), 'NuevoOficio'), 403);
        $legacy = DB::connection('legacy')->table('tm_categoria_widi')
            ->where('cat_id', $category)->where('est', 1)->exists();
        abort_unless($legacy, 404);
        $catalog = DB::table('ccyf_catalogos')->where('legacy_cat_id', $category)->first();
        abort_unless($catalog, 404);
        $products = DB::table('ccyf_productos')->where('catalogo_id', $catalog->id)
            ->where('activo', true)->orderBy('orden')->get();
        abort_if($products->isEmpty(), 422, 'Esta convocatoria no tiene productos activos.');

        $data = Validator::make($request->all(), [
            'tipo_documento_id' => ['required', 'integer', Rule::exists('ccyf_tipos_documento', 'id')->where('activo', true)],
            'precios' => ['required', 'array'],
            'precios.*' => ['required', 'numeric', 'between:0,99999999.99', 'decimal:0,2'],
        ])->validate();
        $expected = $products->pluck('id')->map(fn ($id) => (int) $id)->all();
        sort($expected);
        $received = array_keys($data['precios']);
        sort($received);
        if ($received !== $expected) {
            return back()->withErrors(['precios' => 'El catálogo cambió. Recarga la página y vuelve a capturar los precios.']);
        }

        DB::transaction(function () use ($catalog, $request, $products, $data): void {
            $where = ['catalogo_id' => $catalog->id, 'usu_id' => $request->user()->getKey()];
            $draft = DB::table('ccyf_precio_borradores')->where($where)->first();
            $draftId = $draft?->id ?? DB::table('ccyf_precio_borradores')->insertGetId($where + [
                'tipo_documento_id' => $data['tipo_documento_id'],
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('ccyf_precio_borrador_detalles')->where('borrador_id', $draftId)->delete();
            foreach ($products as $product) {
                DB::table('ccyf_precio_borrador_detalles')->insert([
                    'borrador_id' => $draftId,
                    'producto_id' => $product->id,
                    'producto_nombre' => $product->nombre,
                    'unidad' => $product->unidad,
                    'precio' => $data['precios'][$product->id],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            DB::table('ccyf_precio_borradores')->where('id', $draftId)->update([
                'tipo_documento_id' => $data['tipo_documento_id'],
                'updated_at' => now(),
            ]);
        });

        return back()->with('status', 'Borrador de precios guardado. Podrás continuar con los documentos cuando terminemos el registro.');
    }

    private function authorizeView(Request $request, LegacyMenu $menu): void
    {
        abort_unless(
            $menu->allows($request->user(), 'NuevoOficio') || $menu->allows($request->user(), 'Categorias_widi'),
            403
        );
    }
}
