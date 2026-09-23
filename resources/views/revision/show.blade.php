@extends('layouts.app')
@push('head')
<style>
    .review-date-review { grid-column:1/-1; padding:16px 18px; border:1px solid #e5d9dd; border-radius:12px; background:#fcf8f9; }
    .review-date-review p { margin:0 0 10px; color:#5f4b54; font-size:.87rem; line-height:1.5; }
    .review-date-review button { margin:0 0 12px; }
    .review-date-confirm { display:flex; align-items:flex-start; gap:10px; font-weight:650; font-size:.88rem; }
    .review-date-confirm input { flex:none; width:18px; height:18px; margin:2px 0 0; accent-color:#611232; }
</style>
@endpush

@section('title', 'Expediente '.$registration->folio)
@section('content')
@php($isContestant = app(\App\Services\LegacyMenu::class)->isContestant(auth()->user()))
<a class="back-link" href="{{ $isContestant || $registration->estado === 'Finalizado' ? route('revision.finished') : route('revision.pending') }}">← {{ $isContestant ? 'Mis registros' : ($registration->estado === 'Finalizado' ? 'Convocatorias finalizadas' : 'Convocatorias pendientes') }}</a>
<div class="page-heading review-heading"><div><span class="eyebrow">Expediente actual · {{ $registration->folio }}</span><h1>{{ $registration->solicitante }}</h1><p>{{ $registration->convocatoria_nombre }} · {{ $registration->servicio_nombre }} · {{ $registration->plantel_nombre }}</p></div><span class="pill {{ $registration->estado === 'Finalizado' ? 'pill-ready' : 'pill-pending' }}">{{ $registration->estado }}</span></div>
<div class="review-detail-grid">
    <section class="panel"><span class="eyebrow">01 · Datos del registro</span><h2>Propuesta recibida</h2><dl class="review-facts"><div><dt>Folio</dt><dd>{{ $registration->folio }}</dd></div><div><dt>Plantel</dt><dd>{{ $registration->plantel_nombre }}</dd></div><div><dt>Servicio</dt><dd>{{ $registration->servicio_nombre }}</dd></div><div><dt>Enviado</dt><dd>{{ \Illuminate\Support\Carbon::parse($registration->enviado_at)->format('d/m/Y H:i') }}</dd></div><div><dt>Dirigido a</dt><dd>{{ $registration->dirigido_a }}</dd></div><div><dt>Comentarios</dt><dd>{{ $registration->comentarios }}</dd></div></dl></section>
    <section class="panel"><span class="eyebrow">02 · Oferta económica</span><h2>Precios propuestos</h2><div class="review-price-list">@foreach ($prices as $price)<div><span>{{ $price->producto_nombre }} <small>{{ $price->unidad }}</small></span><strong>${{ number_format((float) $price->precio, 2) }}</strong></div>@endforeach</div></section>
</div>
<section class="panel"><div class="section-bar"><div><span class="eyebrow">03 · Documentación</span><h2>Expediente digital</h2></div><span class="pill pill-neutral">{{ $files->count() }} archivos PDF</span></div><div class="review-file-grid">@foreach ($files as $file)<a class="review-file" href="{{ route('revision.file', [$registration->id, $file->id]) }}" data-review-document data-document-name="{{ $file->nombre }}" data-document-mime="{{ $file->mime }}"><span aria-hidden="true">PDF</span><strong>{{ $file->nombre }}</strong><small>{{ number_format($file->bytes / 1024) }} KB · Ver en modal ↗</small></a>@endforeach</div></section>
@if ($registration->estado === 'Finalizado')
    <section class="panel review-result"><span class="eyebrow">04 · Resultado</span><h2>{{ match ($registration->decision) { 'designado' => 'Designado', 'no_aceptado' => 'No aceptado', default => 'No designado' } }}</h2><p>{{ $registration->respuesta }}</p>@if ($registration->decision === 'designado')<div class="review-result-facts"><span>Del {{ $registration->fecha_inicio }} al {{ $registration->fecha_fin }}</span><strong>Monto inicial: ${{ number_format((float) $registration->monto, 2) }}</strong></div>@endif</section>
    <section class="panel"><span class="eyebrow">05 · Evaluación</span><h2>Hoja final de evaluación</h2><p>{{ $finalEvaluationAvailable ? 'Consulta el PDF generado desde la prevaluación registrada.' : 'Este expediente no tiene un resultado de prevaluación finalizado.' }}</p>@if ($finalEvaluationAvailable)<a class="button button-link" href="{{ route('revision.final-evaluation-pdf', $registration->id) }}" target="_blank" rel="noopener">Consultar evaluación PDF ↗</a>@endif</section>
@elseif (! $isContestant && app(\App\Services\LegacyMenu::class)->allows(auth()->user(), 'gestionOficio'))
    <section class="panel review-decision"><span class="eyebrow">04 · Resolución</span><h2>Finalizar propuesta</h2><p class="muted">La decisión y la respuesta quedarán guardadas en el expediente.</p>
        @if ($errors->any())<div class="form-errors" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <form method="post" action="{{ route('revision.finish', $registration->id) }}">@csrf
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
            <div class="form-footer"><p>Comprueba los PDF y precios antes de finalizar. Este resultado ya no aparecerá en Pendientes.</p><button class="button" type="submit" onclick="return confirm('¿Finalizar esta propuesta con el resultado indicado?')">Guardar resultado</button></div>
        </form>
    </section>
    @push('scripts')<script src="{{ asset('js/review-designation.js') }}?v=1" defer></script>@endpush
@endif
@include('revision.document-viewer')
@endsection
