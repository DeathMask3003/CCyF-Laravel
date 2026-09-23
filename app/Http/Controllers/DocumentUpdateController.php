<?php

namespace App\Http\Controllers;

use App\Services\DocumentFiles;
use App\Services\DocumentUpdateRecords;
use App\Services\LegacyMenu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\File;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class DocumentUpdateController extends Controller
{
    public function index(Request $request, LegacyMenu $menu, DocumentUpdateRecords $records): View
    {
        $request->validate([
            'servicio' => ['nullable', Rule::in(['cafeteria', 'fotocopiado'])],
            'buscar' => ['nullable', 'string', 'max:150'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $isAdmin = $menu->allows($request->user(), 'actualiza_docs');
        $ids = $this->userIds($request);
        $all = $records->all()->filter(fn ($item) => $isAdmin || in_array($item->owner_id, $ids, true));
        $service = $request->query('servicio')
            ?: (! $isAdmin && $all->where('service', 'cafeteria')->isEmpty()
                && $all->where('service', 'fotocopiado')->isNotEmpty() ? 'fotocopiado' : 'cafeteria');
        $filtered = $all->where('service', $service);
        if ($request->filled('buscar')) {
            $term = mb_strtolower(trim((string) $request->query('buscar')));
            $filtered = $filtered->filter(fn ($item) => collect([$item->registration_id, $item->name,
                $item->email, $item->campus, $item->convocation, $item->curp, $item->rfc])
                ->contains(fn ($value) => str_contains(mb_strtolower((string) $value), $term)));
        }
        $filtered = $filtered->values();
        $page = max(1, LengthAwarePaginator::resolveCurrentPage());
        $rows = new LengthAwarePaginator($filtered->forPage($page, 20)->values(), $filtered->count(), 20, $page,
            ['path' => $request->url(), 'query' => $request->except('page')]);
        return view('documentacion.index', compact('rows', 'service', 'isAdmin') + [
            'totalCafe' => $all->where('service', 'cafeteria')->count(),
            'totalFoto' => $all->where('service', 'fotocopiado')->count(),
        ]);
    }

    public function show(string $key, Request $request, LegacyMenu $menu, DocumentUpdateRecords $records, DocumentFiles $files): View
    {
        $record = $this->authorizedRecord($key, $request, $menu, $records);
        $fields = $records->fields($record, $files);
        $versions = $records->versions($record)->groupBy('field_key');
        return view('documentacion.show', compact('record', 'fields', 'versions'));
    }

    public function update(string $key, Request $request, LegacyMenu $menu, DocumentUpdateRecords $records, DocumentFiles $files): RedirectResponse
    {
        $record = $this->authorizedRecord($key, $request, $menu, $records);
        $allowed = $records->fields($record, $files)->pluck('key')->all();
        $data = $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:15'],
            'files.*' => ['required', File::types(['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx'])->max('10mb')],
        ]);
        if (array_diff(array_keys($data['files']), $allowed)) {
            throw ValidationException::withMessages(['files' => 'Se recibieron documentos que no corresponden al expediente.']);
        }
        foreach ($data['files'] as $file) {
            if (! in_array(strtolower((string) $file->getClientOriginalExtension()),
                ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx'], true)) {
                throw ValidationException::withMessages(['files' => 'La extensión del archivo no está permitida.']);
            }
        }

        $stored = [];
        try {
            DB::transaction(function () use ($data, $record, $request, &$stored): void {
                foreach ($data['files'] as $field => $file) {
                    $extension = strtolower((string) $file->getClientOriginalExtension());
                    $path = $file->storeAs('document-updates/'.$record->origin.'/'.$record->registration_id,
                        (string) \Illuminate\Support\Str::uuid().'.'.$extension, 'local');
                    if (! $path) throw new \RuntimeException('No se pudo guardar el archivo.');
                    $stored[] = $path;
                    DB::table('ccyf_document_updates')->insert([
                        'origin' => $record->origin, 'registration_id' => $record->registration_id,
                        'field_key' => $field, 'original_name' => $file->getClientOriginalName(),
                        'path' => $path, 'mime' => $file->getMimeType() ?: 'application/octet-stream',
                        'bytes' => $file->getSize(), 'uploaded_by' => $request->user()->getKey(),
                        'created_at' => now(),
                    ]);
                }
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($stored);
            throw $exception;
        }

        return redirect()->route('documentacion.show', $record->key)
            ->with('status', count($stored).' '.(count($stored) === 1 ? 'documento actualizado' : 'documentos actualizados').'. Se conservó el historial.');
    }

    public function file(string $key, string $field, Request $request, LegacyMenu $menu,
        DocumentUpdateRecords $records, DocumentFiles $files): BinaryFileResponse
    {
        $record = $this->authorizedRecord($key, $request, $menu, $records);
        abort_unless($records->fields($record, $files)->contains('key', $field), 404);
        $effective = $files->effective($record->origin, $record->registration_id, $field);
        abort_unless($effective, 404);
        return $this->sendFile($effective['path'], $effective['name'], $effective['mime']);
    }

    public function version(string $key, int $version, Request $request, LegacyMenu $menu,
        DocumentUpdateRecords $records, DocumentFiles $files): BinaryFileResponse
    {
        $record = $this->authorizedRecord($key, $request, $menu, $records);
        $item = DB::table('ccyf_document_updates')->where('id', $version)
            ->where('origin', $record->origin)->where('registration_id', $record->registration_id)->first();
        abort_unless($item && $records->fields($record, $files)->contains('key', $item->field_key), 404);
        $path = $files->versionPath($item);
        abort_unless($path, 404);
        return $this->sendFile($path, $item->original_name, $item->mime);
    }

    public function original(string $key, string $field, Request $request, LegacyMenu $menu,
        DocumentUpdateRecords $records, DocumentFiles $files): BinaryFileResponse
    {
        $record = $this->authorizedRecord($key, $request, $menu, $records);
        abort_unless($records->fields($record, $files)->contains('key', $field), 404);
        $original = $files->original($record->origin, $record->registration_id, $field);
        abort_unless($original, 404);
        return $this->sendFile($original['path'], $original['name'], $original['mime']);
    }

    private function authorizedRecord(string $key, Request $request, LegacyMenu $menu, DocumentUpdateRecords $records): object
    {
        abort_unless(preg_match('/^(historico|actual)-[1-9]\d*$/', $key), 404);
        $record = $records->find($key);
        abort_unless($record, 404);
        abort_unless($menu->allows($request->user(), 'actualiza_docs') ||
            in_array($record->owner_id, $this->userIds($request), true), 403);
        return $record;
    }

    private function userIds(Request $request): array
    {
        return array_values(array_unique(array_filter([(int) $request->user()->getKey(), (int) $request->user()->legacy_usu_id])));
    }

    private function sendFile(string $path, string $name, string $mime): BinaryFileResponse
    {
        $inline = in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true);
        $response = $inline ? response()->file($path, ['Content-Type' => $mime])
            : response()->download($path, $name, ['Content-Type' => $mime]);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');
        return $response;
    }
}
