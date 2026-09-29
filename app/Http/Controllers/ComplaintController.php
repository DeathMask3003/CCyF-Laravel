<?php

namespace App\Http\Controllers;

use App\Services\ComplaintRecords;
use App\Services\LegacyMenu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ComplaintController extends Controller
{
    public function index(Request $request, LegacyMenu $menu, ComplaintRecords $records): View
    {
        $this->authorizeMenu($request, $menu);
        $request->validate([
            'vista' => ['nullable', Rule::in(['participaciones', 'manuales'])],
            'servicio' => ['nullable', Rule::in(['cafeteria', 'fotocopiado'])],
            'buscar' => ['nullable', 'string', 'max:150'],
            'plantel' => ['nullable', 'string', 'max:150'],
            'estado' => ['nullable', Rule::in(['con', 'sin', 'activas', 'inactivas'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $view = $request->query('vista', 'participaciones');
        $search = mb_strtolower(trim((string) $request->query('buscar', '')));
        $campus = $request->query('plantel');
        $all = $records->participations();
        $manual = $records->manual();
        $campuses = $all->pluck('plantel')->concat($manual->pluck('plantel'))->filter()->unique()->sort()->values();

        if ($view === 'manuales') {
            $filtered = $manual->filter(fn ($row) => (! $campus || $row->plantel === $campus)
                && (! $search || str_contains(mb_strtolower($row->permisionario.' '.$row->plantel.' '.$row->queja), $search))
                && match ($request->query('estado')) {
                    'activas' => $row->activo, 'inactivas' => ! $row->activo, default => true,
                })->values();
        } else {
            $filtered = $all->filter(fn ($row) => (! $request->filled('servicio') || $row->servicio === $request->query('servicio'))
                && (! $campus || $row->plantel === $campus)
                && (! $search || str_contains(mb_strtolower($row->nombre.' '.$row->correo.' '.$row->plantel.' '.$row->convocatoria), $search))
                && match ($request->query('estado')) {
                    'con' => $row->total > 0, 'sin' => $row->total === 0, default => true,
                });
            $filtered = $filtered->groupBy('persona_key')->map(fn ($items) => (object) [
                'nombre' => $items->first()->nombre, 'correo' => $items->first()->correo,
                'participaciones' => $items->values(), 'total' => $items->count(),
                'buenas' => $items->sum('buenas'), 'malas' => $items->sum('malas'),
            ])->values();
        }
        $page = max(1, LengthAwarePaginator::resolveCurrentPage());
        $perPage = 12;
        $rows = new LengthAwarePaginator($filtered->forPage($page, $perPage)->values(), $filtered->count(), $perPage, $page, [
            'path' => $request->url(), 'query' => $request->query(),
        ]);

        return view('quejas.index', compact('rows', 'view', 'campuses') + [
            'peopleCount' => $all->pluck('persona_key')->unique()->count(),
            'participationCount' => $all->count(),
            'complaintCount' => $all->sum('total'),
            'manualCount' => $manual->where('activo', true)->count(),
        ]);
    }

    public function show(string $origin, int $record, Request $request, LegacyMenu $menu, ComplaintRecords $records): View
    {
        $this->authorizeMenu($request, $menu);
        abort_unless(in_array($origin, ['historico', 'actual'], true), 404);
        $participation = $records->participation($origin, $record);
        abort_unless($participation, 404);
        $history = $records->history($participation);
        return view('quejas.show', compact('participation', 'history'));
    }

    public function store(string $origin, int $record, Request $request, LegacyMenu $menu, ComplaintRecords $records): RedirectResponse
    {
        $this->authorizeMenu($request, $menu);
        abort_unless(in_array($origin, ['historico', 'actual'], true), 404);
        abort_unless($records->participation($origin, $record), 404);
        $data = $request->validate([
            'observacion' => ['required', 'string', 'max:2000'],
            'calificacion' => ['required', Rule::in(['0', '1'])],
            'evidencia' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'],
        ]);
        $file = $request->file('evidencia');
        $path = $file?->store('quejas/'.$origin.'/'.$record, 'local');
        try {
            DB::table('ccyf_quejas_participacion')->insert([
                'origen' => $origin, 'registro_id' => $record,
                'observacion' => trim($data['observacion']), 'calificacion' => (bool) $data['calificacion'],
                'evidencia_ruta' => $path, 'evidencia_nombre' => $file?->getClientOriginalName(),
                'evidencia_mime' => $file?->getMimeType(), 'evidencia_bytes' => $file?->getSize(),
                'creado_por' => $request->user()->getKey(), 'activo' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            if ($path) Storage::disk('local')->delete($path);
            throw $exception;
        }
        return redirect()->route('quejas.show', [$origin, $record])->with('status', 'Observación registrada en el historial.');
    }

    public function evidence(string $origin, int $id, Request $request, LegacyMenu $menu): BinaryFileResponse
    {
        $this->authorizeMenu($request, $menu);
        abort_unless(in_array($origin, ['historico', 'local'], true), 404);
        if ($origin === 'historico') {
            $row = DB::connection('legacy')->table('tm_permisionario_queja')->where('queja_id', $id)
                ->where('queja_estado', 1)->first();
            abort_unless($row && $row->queja_evidencia && preg_match('/^[^\\\\\/]+\.(pdf|jpe?g|png|docx?)$/i', $row->queja_evidencia), 404);
            $root = realpath(config('ccyf.legacy_complaints_root'));
            $path = $root ? realpath($root.DIRECTORY_SEPARATOR.$row->doc_id.DIRECTORY_SEPARATOR.$row->queja_evidencia) : false;
            abort_unless($root && $path && str_starts_with($path, $root.DIRECTORY_SEPARATOR) && is_file($path), 404);
            $name = $row->queja_evidencia;
        } else {
            $row = DB::table('ccyf_quejas_participacion')->where('id', $id)->where('activo', true)->first();
            abort_unless($row && $row->evidencia_ruta
                && preg_match('#^quejas/(historico|actual)/[1-9]\d*/[^/]+$#', $row->evidencia_ruta)
                && Storage::disk('local')->exists($row->evidencia_ruta), 404);
            $path = Storage::disk('local')->path($row->evidencia_ruta);
            $name = basename($row->evidencia_nombre ?: $path);
        }
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $headers = ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];
        if (in_array($extension, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
            $headers['Content-Type'] = match ($extension) {
                'pdf' => 'application/pdf', 'png' => 'image/png', default => 'image/jpeg',
            };
            return response()->file($path, $headers + ['Content-Disposition' => 'inline; filename="'.basename($name).'"']);
        }
        return response()->download($path, $name, $headers);
    }

    public function createManual(Request $request, LegacyMenu $menu): View
    {
        $this->authorizeMenu($request, $menu);
        return view('quejas.manual-form', ['complaint' => null, 'campuses' => $this->activeCampuses()]);
    }

    public function storeManual(Request $request, LegacyMenu $menu): RedirectResponse
    {
        $this->authorizeMenu($request, $menu);
        $data = $this->manualData($request);
        DB::table('ccyf_quejas_manuales')->insert($data + [
            'creado_por' => $request->user()->getKey(), 'activo' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return redirect()->route('quejas.index', ['vista' => 'manuales'])->with('status', 'Queja manual registrada.');
    }

    public function editManual(int $complaint, Request $request, LegacyMenu $menu): View
    {
        $this->authorizeMenu($request, $menu);
        $row = DB::table('ccyf_quejas_manuales')->where('id', $complaint)->first();
        abort_unless($row, 404);
        return view('quejas.manual-form', ['complaint' => $row, 'campuses' => $this->activeCampuses()]);
    }

    public function updateManual(int $complaint, Request $request, LegacyMenu $menu): RedirectResponse
    {
        $this->authorizeMenu($request, $menu);
        abort_unless(DB::table('ccyf_quejas_manuales')->where('id', $complaint)->exists(), 404);
        DB::table('ccyf_quejas_manuales')->where('id', $complaint)->update($this->manualData($request) + ['updated_at' => now()]);
        return redirect()->route('quejas.index', ['vista' => 'manuales'])->with('status', 'Queja manual actualizada.');
    }

    public function toggleManual(int $complaint, Request $request, LegacyMenu $menu): RedirectResponse
    {
        $this->authorizeMenu($request, $menu);
        $row = DB::table('ccyf_quejas_manuales')->where('id', $complaint)->first();
        abort_unless($row, 404);
        DB::table('ccyf_quejas_manuales')->where('id', $complaint)->update([
            'activo' => ! $row->activo, 'updated_at' => now(),
        ]);
        return redirect()->route('quejas.index', ['vista' => 'manuales'])->with('status',
            $row->activo ? 'Queja archivada.' : 'Queja reactivada.');
    }

    private function manualData(Request $request): array
    {
        $data = $request->validate([
            'plantel_id' => ['required', 'integer', Rule::exists('ccyf_planteles', 'id')->where('activo', true)],
            'permisionario' => ['required', 'string', 'max:150'],
            'queja' => ['required', 'string', 'max:2000'],
        ]);
        $name = preg_replace('/\s+/u', ' ', trim($data['permisionario']));
        if (count(explode(' ', $name)) < 2) {
            throw ValidationException::withMessages(['permisionario' => 'Captura nombre y apellido del permisionario.']);
        }
        return [
            'plantel_id' => (int) $data['plantel_id'],
            'permisionario' => mb_convert_case(mb_strtolower($name), MB_CASE_TITLE, 'UTF-8'),
            'queja' => trim($data['queja']),
        ];
    }

    private function activeCampuses()
    {
        return DB::table('ccyf_planteles')->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']);
    }

    private function authorizeMenu(Request $request, LegacyMenu $menu): void
    {
        abort_unless($menu->allows($request->user(), 'quejas'), 403);
    }
}
