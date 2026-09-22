<?php

namespace App\Http\Controllers;

use App\Services\LegacyMenu;
use App\Services\PermitTrackingRecords;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class PermitTrackingController extends Controller
{
    public function index(Request $request, LegacyMenu $menu, PermitTrackingRecords $records): View
    {
        $this->authorizeMenu($request, $menu);
        $request->validate([
            'servicio' => ['nullable', Rule::in(['cafeteria', 'fotocopiado'])],
            'mes_inicio' => ['nullable', 'date_format:Y-m'],
            'mes_fin' => ['nullable', 'date_format:Y-m'],
            'buscar' => ['nullable', 'string', 'max:150'],
            'por_pagina' => ['nullable', Rule::in(['10', '25', '50', '100'])],
            'orden' => ['nullable', Rule::in(['registro_at', 'registro_id', 'convocatoria', 'plantel', 'nombre', 'fecha_inicio', 'fecha_fin', 'monto', 'estado'])],
            'direccion' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $all = $records->all();
        $filtered = $records->filtered($request);
        $page = max(1, LengthAwarePaginator::resolveCurrentPage());
        $perPage = (int) $request->query('por_pagina', 25);
        $rows = new LengthAwarePaginator($filtered->forPage($page, $perPage)->values(), $filtered->count(), $perPage, $page, [
            'path' => $request->url(), 'query' => $request->query(),
        ]);

        return view('seguimiento.index', [
            'rows' => $rows, 'totalCafe' => $all->where('servicio', 'cafeteria')->count(),
            'totalFoto' => $all->where('servicio', 'fotocopiado')->count(),
            'service' => $request->query('servicio', 'cafeteria'),
        ]);
    }

    public function save(string $key, Request $request, LegacyMenu $menu, PermitTrackingRecords $records): RedirectResponse
    {
        $this->authorizeMenu($request, $menu);
        $row = $this->record($records, $key);
        $data = $request->validate([
            'metros_cuadrados' => ['required', 'numeric', 'between:0.01,99999999.99', 'decimal:0,2'],
            'matricula' => ['required', 'string', 'max:80'],
            'monto' => ['required', 'numeric', 'between:0,9999999999.99', 'decimal:0,2'],
            'convocatoria1' => ['nullable', 'date'], 'convocatoria2' => ['nullable', 'date'], 'convocatoria3' => ['nullable', 'date'],
            'pagosalmes' => ['nullable', Rule::in(['Al corriente', 'Con adeudo', 'Sin Servicio'])],
            'construidapor' => ['nullable', Rule::in(['Permisionario', 'Padres de familia', 'IMIFE', 'CoBaEM', 'Sin Datos', 'Ayuntamiento', 'Otro'])],
            'servicioenergia' => ['nullable', Rule::in(['Plantel', 'Comisión Federal de Electricidad', 'Medidor', 'Toma Directa', 'Sin Servicio de Luz'])],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'archivo1' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'archivo2' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'archivo3' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'archivo4' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'archivo5' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
        ]);

        $stored = [];
        try {
            foreach (range(1, 5) as $number) {
                $field = 'archivo'.$number;
                if ($request->hasFile($field)) {
                    $stored[$number] = [
                        'ruta' => $request->file($field)->store('seguimientos/'.$row->origen.'/'.$row->registro_id, 'local'),
                        'nombre' => $request->file($field)->getClientOriginalName(),
                    ];
                }
            }
            DB::transaction(function () use ($row, $data, $stored, $request): void {
                $id = DB::table('ccyf_seguimientos')->where('origen', $row->origen)->where('registro_id', $row->registro_id)->value('id');
                $values = [
                    'metros_cuadrados' => $data['metros_cuadrados'], 'matricula' => trim($data['matricula']),
                    'monto' => $data['monto'], 'convocatoria1' => $data['convocatoria1'] ?? null,
                    'convocatoria2' => $data['convocatoria2'] ?? null, 'convocatoria3' => $data['convocatoria3'] ?? null,
                    'pagosalmes' => $data['pagosalmes'] ?? null, 'construidapor' => $data['construidapor'] ?? null,
                    'servicioenergia' => $data['servicioenergia'] ?? null, 'observaciones' => $data['observaciones'] ?? null,
                    'actualizado_por' => $request->user()->getKey(), 'updated_at' => now(),
                ];
                if ($id) {
                    DB::table('ccyf_seguimientos')->where('id', $id)->update($values);
                } else {
                    $id = DB::table('ccyf_seguimientos')->insertGetId($values + [
                        'origen' => $row->origen, 'registro_id' => $row->registro_id, 'created_at' => now(),
                    ]);
                }
                foreach ($stored as $number => $file) {
                    DB::table('ccyf_seguimiento_archivos')->updateOrInsert(
                        ['seguimiento_id' => $id, 'numero' => $number],
                        ['origen' => 'local', 'ruta' => $file['ruta'], 'nombre' => $file['nombre'], 'updated_at' => now(), 'created_at' => now()],
                    );
                }
            });
        } catch (\Throwable $exception) {
            foreach ($stored as $file) {
                Storage::disk('local')->delete($file['ruta']);
            }
            throw $exception;
        }

        return redirect()->route('seguimiento.index', $request->only(['servicio', 'buscar', 'mes_inicio', 'mes_fin', 'por_pagina', 'page']) + ['abrir' => $key])
            ->with('status', 'Seguimiento guardado correctamente.');
    }

    public function renew(string $key, Request $request, LegacyMenu $menu, PermitTrackingRecords $records): RedirectResponse
    {
        $this->authorizeMenu($request, $menu);
        $row = $this->record($records, $key);
        $data = $request->validate([
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
        ]);
        $values = [
            'fecha_inicio_renovada' => $data['fecha_inicio'], 'fecha_fin_renovada' => $data['fecha_fin'],
            'actualizado_por' => $request->user()->getKey(), 'updated_at' => now(),
        ];
        $existing = DB::table('ccyf_seguimientos')->where('origen', $row->origen)->where('registro_id', $row->registro_id);
        if ($existing->exists()) {
            $existing->update($values);
        } else {
            DB::table('ccyf_seguimientos')->insert($values + [
                'origen' => $row->origen, 'registro_id' => $row->registro_id, 'created_at' => now(),
            ]);
        }
        return redirect()->route('seguimiento.index', $request->only(['servicio', 'buscar', 'mes_inicio', 'mes_fin', 'por_pagina', 'page']) + ['abrir' => $key])
            ->with('status', 'Vigencia del contrato renovada correctamente.');
    }

    public function file(string $key, int $number, Request $request, LegacyMenu $menu, PermitTrackingRecords $records): BinaryFileResponse
    {
        $this->authorizeMenu($request, $menu);
        abort_unless($number >= 1 && $number <= 5, 404);
        $row = $this->record($records, $key);
        $file = $row->archivos->get($number);
        abort_unless($file, 404);
        $path = $this->filePath($file);
        abort_unless($path, 404);
        return response()->file($path, [
            'Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="archivo-'.$number.'.pdf"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function zip(string $key, Request $request, LegacyMenu $menu, PermitTrackingRecords $records): BinaryFileResponse
    {
        $this->authorizeMenu($request, $menu);
        $row = $this->record($records, $key);
        $path = tempnam(sys_get_temp_dir(), 'ccyf-zip-');
        abort_unless($path, 500);
        $zip = new ZipArchive;
        abort_unless($zip->open($path, ZipArchive::OVERWRITE) === true, 500);
        $summary = "EXPEDIENTE CCyF\nRegistro: {$row->registro_id}\nServicio: {$row->servicio}\n".
            "Permisionario: {$row->nombre}\nPlantel: {$row->plantel}\nConvocatoria: {$row->convocatoria}\n".
            "Vigencia: {$row->fecha_inicio} al {$row->fecha_fin}\n".
            "Metros cuadrados: ".($row->seguimiento?->metros_cuadrados ?? '')."\n".
            "Matrícula: ".($row->seguimiento?->matricula ?? '')."\n".
            "Monto: ".($row->seguimiento?->monto ?? $row->monto_base ?? '')."\n".
            "Observaciones: ".($row->seguimiento?->observaciones ?? '')."\n";
        $zip->addFromString('00_INFORMACION/RESUMEN_EXPEDIENTE.txt', $summary);
        foreach ($row->archivos as $number => $file) {
            $source = $this->filePath($file);
            if ($source) {
                $zip->addFile($source, '05_SEGUIMIENTO/archivo_'.$number.'.pdf');
            }
        }
        if ($row->origen === 'historico') {
            $root = realpath(config('ccyf.legacy_files_root'));
            $folder = $root ? realpath($root.DIRECTORY_SEPARATOR.$row->registro_id) : false;
            if ($root && $folder && str_starts_with($folder, $root.DIRECTORY_SEPARATOR)) {
                foreach (new \DirectoryIterator($folder) as $file) {
                    if ($file->isFile() && ! $file->isLink() && strtolower($file->getExtension()) === 'pdf') {
                        $zip->addFile($file->getPathname(), '01_DOCUMENTACION_SUBIDA/'.$file->getBasename());
                    }
                }
            }
        } else {
            $documents = DB::table('ccyf_registro_archivos')->where('registro_id', $row->registro_id)->get();
            foreach ($documents as $document) {
                if (Storage::disk('local')->exists($document->ruta)) {
                    $zip->addFile(Storage::disk('local')->path($document->ruta), '01_DOCUMENTACION_SUBIDA/'.$document->id.'.pdf');
                }
            }
        }
        $zip->close();

        return response()->download($path, 'CCyF-expediente-'.$key.'.zip', ['Content-Type' => 'application/zip'])->deleteFileAfterSend(true);
    }

    private function filePath(object $file): ?string
    {
        if ($file->origen === 'local') {
            return str_starts_with($file->ruta, 'seguimientos/') && Storage::disk('local')->exists($file->ruta)
                ? Storage::disk('local')->path($file->ruta) : null;
        }
        if ($file->origen !== 'historico' || str_contains($file->ruta, '\\') ||
            ! preg_match('#^assets/documentos_perm(?:isonarios|isionarios_foto)/[^/]+\.pdf$#i', $file->ruta)) {
            return null;
        }
        $root = realpath(config('ccyf.legacy_root'));
        $path = $root ? realpath($root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $file->ruta)) : false;
        return $root && $path && str_starts_with($path, $root.DIRECTORY_SEPARATOR) && is_file($path) ? $path : null;
    }

    private function record(PermitTrackingRecords $records, string $key): object
    {
        abort_unless(preg_match('/^(historico|actual)-[1-9]\d*$/', $key), 404);
        return $records->find($key) ?? abort(404);
    }

    private function authorizeMenu(Request $request, LegacyMenu $menu): void
    {
        abort_unless($menu->allows($request->user(), 'seguimiento_permisionarios'), 403);
    }
}
