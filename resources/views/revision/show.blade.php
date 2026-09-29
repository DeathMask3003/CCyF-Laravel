@extends('layouts.app')
@push('head')
<link rel="stylesheet" href="{{ asset('css/review-detail.css') }}?v=20260923-1">
<link rel="stylesheet" href="{{ asset('css/review-confirm.css') }}?v=20260927-1">
<style>
    .review-date-review { grid-column:1/-1; padding:16px 18px; border:1px solid #e5d9dd; border-radius:12px; background:#fcf8f9; }
    .review-date-review p { margin:0 0 10px; color:#5f4b54; font-size:.87rem; line-height:1.5; }
    .review-date-review button { margin:0 0 12px; }
    .review-date-confirm { display:flex; align-items:flex-start; gap:10px; font-weight:650; font-size:.88rem; }
    .review-date-confirm input { flex:none; width:18px; height:18px; margin:2px 0 0; accent-color:#611232; }
    .result-mailing { display:grid; gap:16px; }
    .result-mailing-list { display:grid; gap:8px; margin:0; padding:0; list-style:none; }
    .result-mailing-list li { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:10px; padding:12px 14px; border:1px solid #e9e0e3; border-radius:11px; background:#fff; }
    .result-mailing-list small { display:block; color:#766b70; margin-top:3px; }
    .result-mailing-status { display:inline-block; padding:5px 9px; border-radius:999px; font-size:.76rem; font-weight:700; background:#f7edf1; color:#611232; }
    .result-mailing-status.sent { background:#e8f5ec; color:#285e3d; }
    .result-mailing-status.failed { background:#fff2e4; color:#87521c; }
</style>
@endpush

@section('title', 'Expediente '.$registration->folio)
@section('content')
@php($isContestant = app(\App\Services\LegacyMenu::class)->isContestant(auth()->user()))
<a class="back-link" href="{{ $isContestant || $registration->estado === 'Finalizado' ? route('revision.finished') : route('revision.pending') }}">← {{ $isContestant ? 'Mis registros' : ($registration->estado === 'Finalizado' ? 'Convocatorias finalizadas' : 'Convocatorias pendientes') }}</a>
<div class="page-heading review-heading expediente-heading"><div><span class="eyebrow">Expediente · {{ $registration->folio }}</span><h1>{{ $registration->solicitante }}</h1><p>{{ $registration->convocatoria_nombre }} · {{ $registration->servicio_nombre }} · {{ $registration->plantel_nombre }}</p></div><span class="pill {{ $registration->estado === 'Finalizado' ? 'pill-ready' : 'pill-pending' }}">{{ $registration->estado }}</span></div>
<div class="expediente-summary" aria-label="Resumen del expediente"><div><span>Folio</span><strong>{{ $registration->folio }}</strong></div><div><span>Servicio</span><strong>{{ $registration->servicio_nombre }}</strong></div><div><span>Productos ofertados</span><strong>{{ $prices->count() }}</strong></div><div><span>Documentos</span><strong>{{ $files->count() }}</strong></div></div>
<div class="review-detail-grid">
    <section class="panel"><span class="eyebrow">01 · Datos del registro</span><h2>Propuesta recibida</h2><dl class="review-facts"><div><dt>Plantel</dt><dd>{{ $registration->plantel_nombre }}</dd></div><div><dt>Convocatoria</dt><dd>{{ $registration->convocatoria_nombre }}</dd></div><div><dt>Enviado</dt><dd>{{ \Illuminate\Support\Carbon::parse($registration->enviado_at)->format('d/m/Y · H:i') }}</dd></div><div><dt>Dirigido a</dt><dd>{{ $registration->dirigido_a ?: 'No especificado' }}</dd></div></dl>@if ($registration->comentarios)<div class="expediente-note"><span>Comentarios del participante</span><p>{{ $registration->comentarios }}</p></div>@endif</section>
    <section class="panel"><div class="section-bar"><div><span class="eyebrow">02 · Oferta económica</span><h2>Precios propuestos</h2><p class="muted">Importes presentados por producto o servicio.</p></div><span class="pill pill-neutral">{{ $prices->count() }} productos</span></div><div class="review-price-list">@forelse ($prices as $price)<div><span>{{ $price->producto_nombre }} <small>{{ $price->unidad }}</small></span><strong>${{ number_format((float) $price->precio, 2) }}</strong></div>@empty<p class="muted">Este expediente no tiene precios registrados.</p>@endforelse</div></section>
</div>
<section class="panel expediente-documents"><div class="section-bar"><div><span class="eyebrow">03 · Documentación</span><h2>Expediente digital</h2><p class="muted">Selecciona un documento para revisarlo aquí.</p></div><span class="pill pill-neutral">{{ $files->count() }} archivos</span></div><div class="review-file-grid">@forelse ($files as $file)<a class="review-file" href="{{ route('revision.file', [$registration->id, $file->id]) }}" data-review-document data-document-name="{{ $file->nombre }}" data-document-mime="{{ $file->mime }}"><span aria-hidden="true">{{ str_contains($file->mime ?? '', 'pdf') ? 'PDF' : 'IMG' }}</span><strong>{{ $file->nombre }}</strong><small>{{ number_format($file->bytes / 1024) }} KB · Ver en modal ↗</small></a>@empty<p class="muted">No hay documentos adjuntos para este expediente.</p>@endforelse</div></section>
@if ($registration->estado === 'Finalizado')
    <section class="panel review-result"><span class="eyebrow">04 · Resultado</span><h2>{{ match ($registration->decision) { 'designado' => 'Designado', 'no_aceptado' => 'No aceptado', default => 'No designado' } }}</h2><p>{{ $registration->respuesta }}</p>@if ($registration->decision === 'designado')<div class="review-result-facts"><span>Del {{ $registration->fecha_inicio }} al {{ $registration->fecha_fin }}</span><strong>Monto inicial: ${{ number_format((float) $registration->monto, 2) }}</strong></div>@endif
    @if(in_array($registration->decision, ['designado', 'no_designado', 'no_aceptado'], true))<a class="button button-link" href="{{ route('revision.result-letter-pdf', $registration->id) }}" target="_blank" rel="noopener">{{ match ($registration->decision) { 'designado' => 'Ver carta de designación PDF', 'no_aceptado' => 'Ver carta de no aceptación PDF', default => 'Ver carta de no designación PDF' } }} ↗</a>@endif</section>
    <section class="panel"><span class="eyebrow">05 · Evaluación</span><h2>Hoja final de evaluación</h2><p>{{ $finalEvaluationAvailable ? 'Consulta el PDF generado desde la prevaluación registrada.' : 'Este expediente no tiene un resultado de prevaluación finalizado.' }}</p>@if ($finalEvaluationAvailable)<a class="button button-link" href="{{ route('revision.final-evaluation-pdf', $registration->id) }}" target="_blank" rel="noopener">Consultar evaluación PDF ↗</a>@endif</section>
    @if ($canNotify)
        <section class="panel result-mailing"><div><span class="eyebrow">06 · Comunicaciones</span><h2>Notificación del resultado</h2><p class="muted">La persona participante recibe la carta PDF. Si fue designada, Unidad Jurídica recibe también todos los documentos del registro, con copia al plantel y las áreas correspondientes.</p></div>
            @unless ($notificationReady)<p class="expediente-note">El correo está configurado solo para registro. Ningún mensaje se entrega hasta configurar un servidor de correo real.</p>@endunless
            <ul class="result-mailing-list">@forelse ($notificationRows as $mailing)<li><span><strong>{{ $mailing->audience === 'internal' ? 'Unidad Jurídica y áreas' : 'Participante' }}</strong><small>{{ $mailing->recipient }} · {{ $mailing->attachment_count }} {{ $mailing->attachment_count === 1 ? 'adjunto' : 'adjuntos' }}@if ($mailing->part > 1) · parte {{ $mailing->part }}@endif</small>@if ($mailing->last_error && $mailing->status !== 'sent')<small>{{ $mailing->last_error }}</small>@endif</span><span class="result-mailing-status {{ $mailing->status }}">{{ match ($mailing->status) { 'sent' => 'Enviado', 'failed' => 'Falló', 'sending' => 'En proceso', default => 'Pendiente' } }}</span></li>@empty<li>Aún no se ha preparado el envío de este resultado.</li>@endforelse</ul>
            @if ($notificationReady && ($notificationRows->isEmpty() || $notificationRows->contains(fn ($row) => in_array($row->status, ['pending', 'failed'], true))))<form method="post" action="{{ route('revision.notify', $registration->id) }}">@csrf<button class="button" type="submit">Enviar correos pendientes</button></form>@endif
        </section>
    @endif
@elseif (! $isContestant && app(\App\Services\LegacyMenu::class)->allows(auth()->user(), 'gestionOficio'))
    <section class="panel review-decision expediente-decision"><span class="eyebrow">04 · Resolución</span><h2>Finalizar propuesta</h2><p class="muted">La decisión y la respuesta quedarán guardadas en el expediente.</p>
        @if ($errors->any())<div class="form-errors" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <form method="post" action="{{ route('revision.finish', $registration->id) }}" data-review-confirm="single" data-review-folio="{{ $registration->folio }}" data-review-service="{{ $registration->servicio_nombre }}" data-review-campus="{{ $registration->plantel_nombre }}">@csrf
            <div class="review-decision-fields"><label>Decisión<select name="decision" id="decision" required><option value="">Selecciona el resultado</option><option value="designado" @selected(old('decision') === 'designado')>Designar al plantel</option><option value="no_designado" @selected(old('decision') === 'no_designado')>No designar</option><option value="no_aceptado" @selected(old('decision') === 'no_aceptado')>No aceptar propuesta</option></select></label><label>Respuesta para el expediente<textarea name="respuesta" rows="3" maxlength="250" required placeholder="Explica el resultado en un máximo de 250 caracteres">{{ old('respuesta') }}</textarea></label></div>
            <div class="review-designation" id="designation-fields" hidden>
                <label>Fecha de inicio<input id="fecha-inicio" type="date" name="fecha_inicio" value="{{ old('fecha_inicio') }}"></label>
                <label>Fecha de fin<input id="fecha-fin" type="date" name="fecha_fin" value="{{ old('fecha_fin') }}" aria-describedby="fecha-fin-ayuda"></label>
                <label>Monto inicial ($)<input type="number" name="monto" min="0.01" step="0.01" value="{{ old('monto') }}"></label>
                <div class="review-date-review">
                    <p id="fecha-fin-ayuda" role="status" aria-live="polite">Selecciona la fecha de inicio para calcular un año. Puedes cambiar la fecha final manualmente.</p>
                    <button class="quiet-button" id="fecha-fin-sugerida" type="button">Usar fecha sugerida</button>
                    <label class="review-date-confirm"><input id="fecha-fin-confirmada" type="checkbox" name="fecha_fin_confirmada" value="1" @checked(old('fecha_fin_confirmada'))><span>Confirmo que revisé la fecha de fin y es correcta.</span></label>
                </div>
            </div>
            <div class="form-footer"><p>Comprueba los PDF y precios antes de finalizar. Este resultado ya no aparecerá en Pendientes.</p><button class="button" type="submit">Guardar resultado</button></div>
        </form>
    </section>
    @include('revision.partials.confirm-dialog')
    @push('scripts')<script src="{{ asset('js/review-designation.js') }}?v=1" defer></script><script src="{{ asset('js/review-confirm.js') }}?v=20260927-1" defer></script>@endpush
@endif
@include('revision.document-viewer')
@endsection
