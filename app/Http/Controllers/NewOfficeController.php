<?php

namespace App\Http\Controllers;

use App\Services\LegacyMenu;
use App\Services\RegistrationRequirements;
use App\Services\TurnstileVerification;
use App\Rules\PdfDocument;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class NewOfficeController extends Controller
{
    private const RECIPIENT = 'Mtro. Víctor Manuel González de la Mora';

    public function index(Request $request, LegacyMenu $menu): View
    {
        $this->authorizeView($request, $menu);
        $categories = DB::table('ccyf_convocatorias')->where('activo', true)
            ->get(['id as cat_id', 'numero as cat_nom'])->keyBy('cat_id');
        $catalogs = DB::table('ccyf_catalogos')
            ->join('ccyf_tipos_servicio as servicio', 'servicio.id', '=', 'ccyf_catalogos.servicio_id')
            ->where('servicio.activo', true)->whereIn('convocatoria_id', $categories->keys())
            ->orderByDesc('convocatoria_id')->get(['ccyf_catalogos.*', 'servicio.nombre as servicio_nombre']);
        $canSave = $menu->allows($request->user(), 'NuevoOficio');
        $records = DB::table('ccyf_registros')->where('usu_id', $request->user()->getKey())
            ->whereIn('convocatoria_id', $categories->keys())
            ->selectRaw('convocatoria_id, count(*) total')->groupBy('convocatoria_id')
            ->pluck('total', 'convocatoria_id');

        return view('oficios.index', compact('categories', 'catalogs', 'canSave', 'records'));
    }

    public function show(int $category, Request $request, LegacyMenu $menu, RegistrationRequirements $requirements): View
    {
        $this->authorizeView($request, $menu);
        [$convocation, $catalog, $products] = $this->context($category);
        $draft = DB::table('ccyf_precio_borradores')->where('catalogo_id', $catalog->id)
            ->where('usu_id', $request->user()->getKey())->first();
        $saved = $draft ? DB::table('ccyf_precio_borrador_detalles')
            ->where('borrador_id', $draft->id)->pluck('precio', 'producto_id') : collect();
        $documentTypes = DB::table('ccyf_tipos_documento')->where('activo', true)
            ->orderBy('nombre')->get(['id', 'nombre']);
        $campuses = DB::table('ccyf_convocatoria_planteles as asignacion')
            ->join('ccyf_planteles as plantel', 'plantel.id', '=', 'asignacion.plantel_id')
            ->where('asignacion.convocatoria_id', $category)->where('plantel.activo', true)
            ->orderBy('plantel.nombre')->get(['plantel.id', 'plantel.nombre']);
        $submittedCampusIds = DB::table('ccyf_registros')->where('usu_id', $request->user()->getKey())
            ->where('convocatoria_id', $category)->pluck('plantel_id')->map(fn ($id) => (int) $id)->all();
        $requiredDocuments = $requirements->activeFor((int) $catalog->servicio_id);
        $canSave = $menu->allows($request->user(), 'NuevoOficio');
        $recipient = self::RECIPIENT;
        $legacy = $convocation;

        return view('oficios.show', compact(
            'legacy', 'catalog', 'products', 'saved', 'draft', 'documentTypes', 'campuses',
            'submittedCampusIds', 'requiredDocuments', 'canSave', 'recipient'
        ));
    }

    public function save(int $category, Request $request, LegacyMenu $menu): RedirectResponse
    {
        abort_unless($menu->allows($request->user(), 'NuevoOficio'), 403);
        [, $catalog, $products] = $this->context($category);
        $data = $this->validateCore($request, $category, $products);

        DB::transaction(function () use ($catalog, $request, $products, $data): void {
            $where = ['catalogo_id' => $catalog->id, 'usu_id' => $request->user()->getKey()];
            $draft = DB::table('ccyf_precio_borradores')->where($where)->first();
            $draftId = $draft?->id ?? DB::table('ccyf_precio_borradores')->insertGetId($where + [
                'tipo_documento_id' => $data['tipo_documento_id'],
                'plantel_id' => $data['plantel_id'],
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('ccyf_precio_borrador_detalles')->where('borrador_id', $draftId)->delete();
            foreach ($products as $product) {
                DB::table('ccyf_precio_borrador_detalles')->insert([
                    'borrador_id' => $draftId, 'producto_id' => $product->id,
                    'producto_nombre' => $product->nombre, 'unidad' => $product->unidad,
                    'precio' => $data['precios'][$product->id],
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            DB::table('ccyf_precio_borradores')->where('id', $draftId)->update([
                'tipo_documento_id' => $data['tipo_documento_id'], 'plantel_id' => $data['plantel_id'],
                'updated_at' => now(),
            ]);
        });

        return back()->with('status', 'Borrador guardado. Puedes continuar y enviar el registro cuando tengas todos los PDF.');
    }

    public function submit(int $category, Request $request, LegacyMenu $menu, RegistrationRequirements $requirements, TurnstileVerification $turnstile): RedirectResponse
    {
        abort_unless($menu->allows($request->user(), 'NuevoOficio'), 403);
        [, $catalog, $products] = $this->context($category);
        $requiredDocuments = $requirements->activeFor((int) $catalog->servicio_id);
        abort_if($requiredDocuments->isEmpty(), 422, 'Este servicio no tiene requisitos documentales configurados.');
        $rules = [
            'comentarios' => ['required', 'string', 'max:5000'],
            'documentos' => ['required', 'array'],
        ];
        $attributes = [
            'tipo_documento_id' => 'tipo de invitación',
            'plantel_id' => 'plantel',
            'comentarios' => 'comentarios de la propuesta',
            'documentos' => 'documentación',
            'precios' => 'precios',
            'precios.*' => 'precio de producto',
        ];
        foreach ($requiredDocuments as $requirement) {
            $rules['documentos.'.$requirement->clave] = [
                $requirement->requerido ? 'required' : 'nullable', 'file', new PdfDocument($requirement->nombre), 'max:3072',
            ];
            $attributes['documentos.'.$requirement->clave] = $requirement->nombre;
        }
        $data = $this->validateCore($request, $category, $products, $rules, $attributes);

        $turnstile->verify($request, 'submit_registration');

        $expectedFiles = $requiredDocuments->pluck('clave')->sort()->values()->all();
        $receivedFiles = array_keys($request->file('documentos', []));
        sort($receivedFiles);
        if ($receivedFiles !== $expectedFiles) {
            throw ValidationException::withMessages([
                'documentos' => 'La lista de documentos cambió. Recarga la página antes de enviar.',
            ]);
        }
        $duplicate = DB::table('ccyf_registros')->where([
            'usu_id' => $request->user()->getKey(), 'convocatoria_id' => $category,
            'plantel_id' => $data['plantel_id'], 'servicio_id' => $catalog->servicio_id,
        ])->exists();
        if ($duplicate) {
            throw ValidationException::withMessages([
                'plantel_id' => 'Ya enviaste un registro para este plantel, convocatoria y servicio.',
            ]);
        }

        $recordId = null;
        try {
            DB::transaction(function () use (&$recordId, $category, $catalog, $products, $requiredDocuments, $request, $data): void {
                $now = now();
                $id = DB::table('ccyf_registros')->insertGetId([
                    'convocatoria_id' => $category, 'catalogo_id' => $catalog->id,
                    'servicio_id' => $catalog->servicio_id, 'plantel_id' => $data['plantel_id'],
                    'tipo_documento_id' => $data['tipo_documento_id'], 'usu_id' => $request->user()->getKey(),
                    'solicitante' => $request->user()->usu_area, 'dirigido_a' => self::RECIPIENT,
                    'comentarios' => trim($data['comentarios']), 'estado' => 'Recibido',
                    'enviado_at' => $now, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $recordId = $id;
                $folio = 'CCYF-'.$now->format('Y').'-'.str_pad((string) $id, 5, '0', STR_PAD_LEFT);
                DB::table('ccyf_registros')->where('id', $id)->update(['folio' => $folio]);

                foreach ($products as $product) {
                    DB::table('ccyf_registro_precios')->insert([
                        'registro_id' => $id, 'producto_id' => $product->id,
                        'producto_nombre' => $product->nombre, 'unidad' => $product->unidad,
                        'precio' => $data['precios'][$product->id], 'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
                foreach ($requiredDocuments as $requirement) {
                    $file = $request->file('documentos.'.$requirement->clave);
                    $filename = $requirement->clave.'-'.Str::uuid().'.pdf';
                    $path = $file->storeAs('ccyf/registros/'.$id, $filename, 'local');
                    if (! $path) {
                        throw new \RuntimeException('No fue posible guardar uno de los documentos.');
                    }
                    DB::table('ccyf_registro_archivos')->insert([
                        'registro_id' => $id, 'requisito_id' => $requirement->id,
                        'nombre_original' => $file->getClientOriginalName(), 'ruta' => $path,
                        'mime' => 'application/pdf', 'bytes' => $file->getSize(),
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
                $draft = DB::table('ccyf_precio_borradores')->where([
                    'catalogo_id' => $catalog->id, 'usu_id' => $request->user()->getKey(),
                ])->first();
                if ($draft) {
                    DB::table('ccyf_precio_borrador_detalles')->where('borrador_id', $draft->id)->delete();
                    DB::table('ccyf_precio_borradores')->where('id', $draft->id)->delete();
                }

            });
        } catch (Throwable $exception) {
            if ($recordId) {
                Storage::disk('local')->deleteDirectory('ccyf/registros/'.$recordId);
            }
            if ($exception instanceof UniqueConstraintViolationException && DB::table('ccyf_registros')->where([
                'usu_id' => $request->user()->getKey(), 'convocatoria_id' => $category,
                'plantel_id' => $data['plantel_id'], 'servicio_id' => $catalog->servicio_id,
            ])->exists()) {
                throw ValidationException::withMessages([
                    'plantel_id' => 'Ya enviaste un registro para este plantel, convocatoria y servicio.',
                ]);
            }
            throw $exception;
        }

        return redirect()->route('oficios.receipt', $recordId);
    }

    public function receipt(int $record, Request $request, LegacyMenu $menu): View
    {
        $registration = DB::table('ccyf_registros as registro')
            ->join('ccyf_convocatorias as convocatoria', 'convocatoria.id', '=', 'registro.convocatoria_id')
            ->join('ccyf_tipos_servicio as servicio', 'servicio.id', '=', 'registro.servicio_id')
            ->join('ccyf_planteles as plantel', 'plantel.id', '=', 'registro.plantel_id')
            ->join('ccyf_tipos_documento as tipo', 'tipo.id', '=', 'registro.tipo_documento_id')
            ->where('registro.id', $record)
            ->first(['registro.*', 'convocatoria.numero as convocatoria_nombre', 'servicio.nombre as servicio_nombre', 'plantel.nombre as plantel_nombre', 'tipo.nombre as tipo_nombre']);
        abort_unless($registration, 404);
        $owns = (int) $registration->usu_id === (int) $request->user()->getKey();
        abort_unless($owns || $menu->allows($request->user(), 'Categorias_widi'), 403);
        $files = DB::table('ccyf_registro_archivos')->where('registro_id', $record)->count();

        return view('oficios.receipt', compact('registration', 'files'));
    }

    private function validateCore(Request $request, int $category, $products, array $extra = [], array $attributes = []): array
    {
        abort_if($products->isEmpty(), 422, 'Esta convocatoria no tiene productos activos.');
        $data = Validator::make($request->all(), [
            'tipo_documento_id' => ['required', 'integer', Rule::exists('ccyf_tipos_documento', 'id')->where('activo', true)],
            'plantel_id' => ['required', 'integer', Rule::exists('ccyf_planteles', 'id')->where('activo', true)],
            'precios' => ['required', 'array'],
            'precios.*' => ['required', 'numeric', 'between:0,99999999.99', 'decimal:0,2'],
        ] + $extra, [], $attributes)->validate();
        $assigned = DB::table('ccyf_convocatoria_planteles')->where('convocatoria_id', $category)
            ->where('plantel_id', $data['plantel_id'])->exists();
        if (! $assigned) {
            throw ValidationException::withMessages(['plantel_id' => 'El plantel no participa en esta convocatoria.']);
        }
        $expected = $products->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $received = array_map('intval', array_keys($data['precios']));
        sort($received);
        if ($received !== $expected) {
            throw ValidationException::withMessages([
                'precios' => 'El catálogo cambió. Recarga la página y vuelve a capturar los precios.',
            ]);
        }

        return $data;
    }

    private function context(int $category): array
    {
        $convocation = DB::table('ccyf_convocatorias')->where('id', $category)->where('activo', true)
            ->first(['id as cat_id', 'numero as cat_nom']);
        abort_unless($convocation, 404);
        $catalog = DB::table('ccyf_catalogos')
            ->join('ccyf_tipos_servicio as servicio', 'servicio.id', '=', 'ccyf_catalogos.servicio_id')
            ->where('servicio.activo', true)->where('convocatoria_id', $category)
            ->first(['ccyf_catalogos.*', 'servicio.nombre as servicio_nombre']);
        abort_unless($catalog, 404);
        $products = DB::table('ccyf_productos')->where('catalogo_id', $catalog->id)
            ->where('activo', true)->orderBy('orden')->orderBy('id')->get();

        return [$convocation, $catalog, $products];
    }

    private function authorizeView(Request $request, LegacyMenu $menu): void
    {
        abort_unless(
            $menu->allows($request->user(), 'NuevoOficio') || $menu->allows($request->user(), 'Categorias_widi'),
            403
        );
    }
}
