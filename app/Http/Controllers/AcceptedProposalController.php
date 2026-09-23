<?php

namespace App\Http\Controllers;

use App\Services\AcceptedProposals;
use App\Services\ContractDocuments;
use App\Services\ContractText;
use App\Services\LegacyMenu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AcceptedProposalController extends Controller
{
    public function index(Request $request, LegacyMenu $menu, AcceptedProposals $proposals, ContractDocuments $contracts): View
    {
        $this->authorizeModule($request, $menu);
        $request->validate([
            'servicio' => ['nullable', Rule::in(['cafeteria', 'fotocopiado'])],
            'convocatoria' => ['nullable', 'string', 'max:150'],
            'estado' => ['nullable', Rule::in(['listo', 'incompleto', 'enviado'])],
            'buscar' => ['nullable', 'string', 'max:150'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $all = $proposals->all();
        $service = $request->query('servicio', 'fotocopiado');
        $rows = $all->where('service', $service);
        $calls = $rows->pluck('convocation')->filter()->unique()->sort()->values();
        if ($request->filled('convocatoria')) $rows = $rows->where('convocation', $request->query('convocatoria'));
        if ($request->filled('estado')) {
            $state = $request->query('estado');
            $rows = $rows->filter(fn ($row) => match ($state) {
                'enviado' => $row->sent,
                'incompleto' => ! $row->sent && ($proposals->missing($row, true) || $contracts->issues($row)),
                default => ! $row->sent && $proposals->missing($row, true) === [] && $contracts->issues($row) === [],
            });
        }
        if ($request->filled('buscar')) {
            $term = mb_strtolower(trim((string) $request->query('buscar')));
            $rows = $rows->filter(fn ($row) => collect([$row->folio, $row->name, $row->email,
                $row->campus, $row->convocation])->contains(fn ($value) => str_contains(mb_strtolower((string) $value), $term)));
        }
        $rows = $rows->values();
        $page = max(1, LengthAwarePaginator::resolveCurrentPage());
        $paginated = new LengthAwarePaginator($rows->forPage($page, 20)->values(), $rows->count(), 20, $page,
            ['path' => $request->url(), 'query' => $request->except('page')]);
        $template = $contracts->template($service === 'cafeteria' ? 3 : 4);
        return view('contratos.index', [
            'rows' => $paginated, 'service' => $service, 'calls' => $calls,
            'totalCafe' => $all->where('service', 'cafeteria')->count(),
            'totalFoto' => $all->where('service', 'fotocopiado')->count(),
            'ready' => $all->where('service', $service)->filter(fn ($row) => ! $row->sent
                && $proposals->missing($row, true) === [] && $contracts->issues($row) === [])->count(),
            'sent' => $all->where('service', $service)->filter(fn ($row) => $row->sent)->count(),
            'template' => $template,
            'mailReady' => $this->mailReady(),
            'proposals' => $proposals, 'contracts' => $contracts,
        ]);
    }

    public function show(string $key, Request $request, LegacyMenu $menu, AcceptedProposals $proposals, ContractDocuments $contracts): View
    {
        $this->authorizeModule($request, $menu);
        $row = $this->record($proposals, $key);
        return view('contratos.show', [
            'row' => $row, 'missing' => array_merge($proposals->missing($row, true), $contracts->issues($row)),
            'template' => $contracts->template($row->service_id), 'mailReady' => $this->mailReady(),
            'termHistory' => DB::table('ccyf_contract_terms as t')
                ->leftJoin('ccyf_usuarios as u', 'u.usu_id', '=', 't.updated_by')
                ->where('t.origin', $row->origin)->where('t.registration_id', $row->id)
                ->orderByDesc('t.id')->limit(8)->get(['t.*', 'u.usu_area as editor']),
        ]);
    }

    public function saveTerms(string $key, Request $request, LegacyMenu $menu, AcceptedProposals $proposals): RedirectResponse
    {
        $this->authorizeModule($request, $menu);
        $row = $this->record($proposals, $key);
        abort_if($row->sent || $row->delivery, 409, 'El contrato ya fue enviado o requiere revisión.');
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:180'],
            'amount' => ['nullable', 'numeric', 'between:0.01,9999999999.99'],
            'starts' => ['nullable', 'date'], 'ends' => ['nullable', 'date'],
            'campus_address' => ['nullable', 'string', 'max:500'],
            'email' => ['nullable', 'email', 'max:200'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:500'],
            'ine' => ['nullable', 'string', 'max:40'],
            'alternate' => ['nullable', 'string', 'max:180'],
            'alternate_phone' => ['nullable', 'string', 'max:40'],
        ]);
        $starts = $data['starts'] ?? $row->starts;
        $ends = $data['ends'] ?? $row->ends;
        if ($starts && $ends && strtotime($ends) <= strtotime($starts)) {
            throw ValidationException::withMessages(['ends' => 'El fin de vigencia debe ser posterior al inicio.']);
        }
        if (array_key_exists('name', $data)) {
            $data['name'] = ContractText::personName($data['name']);
        }
        DB::table('ccyf_contract_terms')->insert(['origin' => $row->origin, 'registration_id' => $row->id,
            'name' => $data['name'] ?? null,
            'amount' => $data['amount'] ?? null, 'starts' => $data['starts'] ?? null,
            'ends' => $data['ends'] ?? null, 'campus_address' => $data['campus_address'] ?? null,
            'email' => $data['email'] ?? null, 'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null, 'ine' => $data['ine'] ?? null,
            'alternate' => $data['alternate'] ?? null, 'alternate_phone' => $data['alternate_phone'] ?? null,
            'updated_by' => $request->user()->getKey(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        return redirect()->route('contratos.show', $key)->with('status', 'Datos contractuales guardados como nueva versión.');
    }

    public function preview(string $key, Request $request, LegacyMenu $menu, AcceptedProposals $proposals, ContractDocuments $contracts): Response
    {
        $this->authorizeModule($request, $menu);
        $row = $this->record($proposals, $key);
        abort_unless($contracts->template($row->service_id), 422, 'Configure primero la plantilla.');
        $pdf = $contracts->render($row);
        return response($pdf, 200, ['Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="borrador-contrato-'.$row->id.'.pdf"',
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function sentPdf(string $key, Request $request, LegacyMenu $menu, AcceptedProposals $proposals): Response
    {
        $this->authorizeModule($request, $menu);
        $row = $this->record($proposals, $key);
        $path = $row->delivery?->pdf_path;
        abort_unless($row->delivery?->sent_at && $path && Storage::disk('local')->exists($path), 404);
        return response(Storage::disk('local')->get($path), 200, ['Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="contrato-'.$row->id.'.pdf"',
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function template(string $service, Request $request, LegacyMenu $menu, ContractDocuments $contracts): View
    {
        $this->authorizeModule($request, $menu);
        $request->validate(['version' => ['nullable', 'integer', 'min:1']]);
        $serviceId = $this->serviceId($service);
        $current = $contracts->template($serviceId);
        $viewingVersion = null;
        if ($request->filled('version')) {
            $viewingVersion = DB::table('ccyf_contract_templates')
                ->where('legacy_trami_id', $serviceId)->where('id', $request->query('version'))->first();
            abort_unless($viewingVersion, 404);
        }
        $editor = $viewingVersion ?: $current;
        $additionalSigners = $viewingVersion
            ? (json_decode((string) $viewingVersion->additional_signers, true) ?: [])
            : ($current?->additional_signers ?? []);
        $versions = DB::table('ccyf_contract_templates as t')
            ->leftJoin('ccyf_usuarios as u', 'u.usu_id', '=', 't.edited_by')
            ->where('t.legacy_trami_id', $serviceId)->orderByDesc('t.id')
            ->get(['t.id', 't.created_at', 'u.usu_area as editor']);
        return view('contratos.template', ['service' => $service, 'current' => $current,
            'editor' => $editor, 'viewingVersion' => $viewingVersion,
            'versions' => $versions, 'variables' => ContractDocuments::VARIABLES,
            'fontFamilies' => ContractDocuments::FONT_FAMILIES,
            'additionalSigners' => $additionalSigners,
            'editorHtml' => $contracts->editorHtml((string) old('body', $editor?->body ?? ''))]);
    }

    public function saveTemplate(string $service, Request $request, LegacyMenu $menu, ContractDocuments $contracts): RedirectResponse
    {
        $this->authorizeModule($request, $menu);
        $serviceId = $this->serviceId($service);
        $data = $request->validate(['body' => ['required', 'string', 'min:100', 'max:700000'],
            'font_family' => ['required', Rule::in(array_keys(ContractDocuments::FONT_FAMILIES))],
            'font_size' => ['required', 'numeric', 'between:7,14', 'multiple_of:0.5'],
            'institution_signer' => ['required', 'string', 'max:180'],
            'institution_role' => ['required', 'string', 'max:180'],
            'witness_signer' => ['required', 'string', 'max:180'],
            'witness_role' => ['required', 'string', 'max:180'],
            'additional_signers' => ['required', 'array', 'size:2'],
            'additional_signers.*.title' => ['required', 'string', 'max:100'],
            'additional_signers.*.name' => ['required', 'string', 'max:180'],
            'additional_signers.*.role' => ['required', 'string', 'max:180'],
            'confirmacion' => ['accepted']]);
        $data['body'] = $contracts->editorHtml($data['body']);
        foreach (['permisionario', 'plantel', 'monto', 'fecha_ini', 'fecha_fin'] as $variable) {
            if (! str_contains($data['body'], '{'.$variable.'}')) {
                throw ValidationException::withMessages(['body' => 'La plantilla debe incluir {'.$variable.'}.']);
            }
        }
        if (preg_match('/\*{5,}/', strip_tags($data['body']))) {
            throw ValidationException::withMessages(['body' => 'La plantilla aún contiene asteriscos que sustituyen datos del contrato.']);
        }
        DB::table('ccyf_contract_templates')->insert([
            'legacy_trami_id' => $serviceId, 'body' => $data['body'],
            'font_family' => $data['font_family'], 'font_size' => $data['font_size'],
            'institution_signer' => $data['institution_signer'], 'institution_role' => $data['institution_role'],
            'witness_signer' => $data['witness_signer'], 'witness_role' => $data['witness_role'],
            'additional_signers' => json_encode(array_values($data['additional_signers']), JSON_UNESCAPED_UNICODE),
            'edited_by' => $request->user()->getKey(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        return redirect()->route('contratos.template', $service)->with('status', 'Nueva versión de la plantilla guardada.');
    }

    public function send(string $key, Request $request, LegacyMenu $menu, AcceptedProposals $proposals, ContractDocuments $contracts): RedirectResponse
    {
        $this->authorizeModule($request, $menu);
        $row = $this->record($proposals, $key);
        $this->deliver($row, $request->user()->getKey(), $proposals, $contracts);
        return redirect()->route('contratos.show', $key)->with('status', 'Contrato enviado a '.$row->email.'.');
    }

    public function sendBulk(Request $request, LegacyMenu $menu, AcceptedProposals $proposals, ContractDocuments $contracts): RedirectResponse
    {
        $this->authorizeModule($request, $menu);
        $data = $request->validate(['keys' => ['required', 'array', 'min:1', 'max:20'],
            'keys.*' => ['required', 'distinct', 'regex:/^(historico|actual)-[1-9]\d*$/']]);
        $sent = 0;
        $failed = [];
        foreach ($data['keys'] as $key) {
            try {
                $this->deliver($this->record($proposals, $key), $request->user()->getKey(), $proposals, $contracts);
                $sent++;
            } catch (Throwable $error) {
                report($error);
                $failed[] = $key;
            }
        }
        return redirect()->route('contratos.index', $request->only(['servicio', 'convocatoria', 'estado', 'buscar', 'page']))
            ->with('status', "Contratos enviados: {$sent}.".($failed ? ' Revisa estos expedientes: '.implode(', ', $failed).'.' : ''));
    }

    private function deliver(object $row, int $userId, AcceptedProposals $proposals, ContractDocuments $contracts): void
    {
        abort_unless($this->mailReady(), 409, 'Configura el correo real antes de enviar contratos.');
        abort_if($row->sent, 409, 'Este contrato ya figura como enviado.');
        $missing = array_merge($proposals->missing($row, true), $contracts->issues($row));
        if ($missing) throw ValidationException::withMessages(['contrato' => 'Completa: '.implode(', ', $missing).'.']);
        $template = $contracts->template($row->service_id);
        abort_unless($template, 422, 'Configure primero la plantilla de este servicio.');

        DB::transaction(function () use ($row, $userId): void {
            DB::table('ccyf_contract_deliveries')->insertOrIgnore([
                'origin' => $row->origin, 'registration_id' => $row->id, 'status' => 'preparing',
                'sent_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $delivery = DB::table('ccyf_contract_deliveries')->where('origin', $row->origin)
                ->where('registration_id', $row->id)->lockForUpdate()->first();
            abort_unless($delivery && $delivery->status === 'preparing' && (int) $delivery->sent_by === $userId,
                409, 'Este contrato ya está enviado o requiere revisión antes de reintentar.');
            DB::table('ccyf_contract_deliveries')->where('id', $delivery->id)
                ->update(['status' => 'generating', 'updated_at' => now()]);
        });

        $path = null;
        try {
            $pdf = $contracts->render($row, false);
            $path = 'contracts/'.$row->origin.'/'.$row->id.'/'.now()->format('YmdHis').'-'.bin2hex(random_bytes(5)).'.pdf';
            Storage::disk('local')->put($path, $pdf);
            DB::table('ccyf_contract_deliveries')->where('origin', $row->origin)->where('registration_id', $row->id)
                ->update(['status' => 'sending', 'recipient' => $row->email, 'pdf_path' => $path,
                    'template_hash' => hash('sha256', $template->body), 'updated_at' => now()]);
            Mail::raw('Adjuntamos el contrato de prestación del servicio para su revisión. Conserve este correo como comprobante de envío.',
                function ($message) use ($row, $pdf): void {
                    $message->to($row->email)->subject('Contrato de permisionario · '.$row->folio)
                        ->attachData($pdf, 'contrato-'.$row->id.'.pdf', ['mime' => 'application/pdf']);
                });
            DB::table('ccyf_contract_deliveries')->where('origin', $row->origin)->where('registration_id', $row->id)
                ->update(['status' => 'sent', 'sent_at' => now(), 'updated_at' => now()]);
        } catch (Throwable $error) {
            DB::table('ccyf_contract_deliveries')->where('origin', $row->origin)->where('registration_id', $row->id)
                ->update(['status' => $path ? 'needs_review' : 'failed',
                    'last_error' => mb_substr($error->getMessage(), 0, 1000), 'updated_at' => now()]);
            throw $error;
        }
    }

    private function mailReady(): bool
    {
        return ! in_array(config('mail.default'), ['log', 'array', 'failover'], true)
            && filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL)
            && ! str_ends_with((string) config('mail.from.address'), '@example.com');
    }

    private function authorizeModule(Request $request, LegacyMenu $menu): void
    {
        abort_unless($menu->allows($request->user(), 'Permisionarios_aceptados_vujeig'), 403);
    }

    private function record(AcceptedProposals $proposals, string $key): object
    {
        abort_unless(preg_match('/^(historico|actual)-[1-9]\d*$/', $key), 404);
        return $proposals->find($key) ?? abort(404);
    }

    private function serviceId(string $service): int
    {
        abort_unless(in_array($service, ['cafeteria', 'fotocopiado'], true), 404);
        return $service === 'cafeteria' ? 3 : 4;
    }
}
