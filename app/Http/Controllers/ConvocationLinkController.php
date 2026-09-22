<?php

namespace App\Http\Controllers;

use App\Services\LegacyMenu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ConvocationLinkController extends Controller
{
    public function index(Request $request, LegacyMenu $menu): View
    {
        $this->authorizeLinks($request, $menu);
        $links = DB::table('ccyf_convocatorias as convocatoria')
            ->leftJoin('ccyf_tipos_servicio as servicio', 'servicio.id', '=', 'convocatoria.servicio_id')
            ->select('convocatoria.*', 'servicio.nombre as servicio_nombre')
            ->selectSub(function ($query): void {
                $query->from('ccyf_convocatoria_planteles')
                    ->whereColumn('convocatoria_id', 'convocatoria.id')->selectRaw('count(*)');
            }, 'planteles_total')
            ->orderByDesc('convocatoria.activo')->orderByDesc('convocatoria.id')->get();

        return view('enlaces.index', compact('links'));
    }

    public function edit(int $convocation, Request $request, LegacyMenu $menu): View
    {
        $this->authorizeLinks($request, $menu);
        $convocation = DB::table('ccyf_convocatorias as convocatoria')
            ->leftJoin('ccyf_tipos_servicio as servicio', 'servicio.id', '=', 'convocatoria.servicio_id')
            ->where('convocatoria.id', $convocation)
            ->first(['convocatoria.*', 'servicio.nombre as servicio_nombre']);
        abort_unless($convocation, 404);
        $campuses = DB::table('ccyf_planteles')->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']);
        $selected = DB::table('ccyf_convocatoria_planteles')->where('convocatoria_id', $convocation->id)
            ->pluck('plantel_id')->map(fn ($id) => (int) $id)->all();

        return view('enlaces.edit', compact('convocation', 'campuses', 'selected'));
    }

    public function update(int $convocation, Request $request, LegacyMenu $menu): RedirectResponse
    {
        $this->authorizeLinks($request, $menu);
        $record = DB::table('ccyf_convocatorias')->where('id', $convocation)->first();
        abort_unless($record, 404);
        $data = $request->validate([
            'planteles' => ['required', 'array', 'min:1'],
            'planteles.*' => ['integer', 'distinct', Rule::exists('ccyf_planteles', 'id')->where('activo', true)],
        ]);

        DB::transaction(function () use ($record, $data): void {
            DB::table('ccyf_convocatoria_planteles')->where('convocatoria_id', $record->id)->delete();
            foreach (array_unique(array_map('intval', $data['planteles'])) as $campusId) {
                DB::table('ccyf_convocatoria_planteles')->insert([
                    'convocatoria_id' => $record->id,
                    'plantel_id' => $campusId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return redirect()->route('enlaces.index')->with('status', 'Enlaces de la convocatoria actualizados correctamente.');
    }

    private function authorizeLinks(Request $request, LegacyMenu $menu): void
    {
        abort_unless($menu->allows($request->user(), 'Subcategorias_widi'), 403);
    }
}
