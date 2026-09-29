@extends('layouts.app')

@push('head')<link rel="stylesheet" href="{{ asset('css/complaints.css') }}?v=20260923-1">@endpush
@section('title', 'Historial de observaciones')
@section('content')
<a class="back-link" href="{{ route('quejas.index') }}">← Volver a observaciones y quejas</a>
<div class="complaint-detail-heading"><div><span class="eyebrow">Participación {{ $participation->key }}</span><h1>{{ $participation->nombre }}</h1><p>{{ $participation->plantel }} · {{ $participation->convocatoria }} · {{ $participation->servicio === 'cafeteria' ? 'Cafetería' : 'Fotocopiado' }}</p></div><span @class(['complaint-status', 'is-good' => $participation->total && $participation->buenas >= $participation->malas, 'is-bad' => $participation->malas > $participation->buenas])>{{ $participation->total ? ($participation->buenas >= $participation->malas ? 'Evaluación favorable' : 'Con incidencias') : 'Sin evaluación' }}</span></div>

<div class="complaint-detail-stats"><div><span>Registros</span><strong>{{ $participation->total }}</strong></div><div><span>Favorables</span><strong>{{ $participation->buenas }}</strong></div><div><span>Incidencias</span><strong>{{ $participation->malas }}</strong></div></div>

<div class="complaint-detail-grid">
    <section class="panel complaint-history" aria-labelledby="complaint-history-title"><div class="complaint-section-head"><span class="eyebrow">Seguimiento</span><h2 id="complaint-history-title">Historial de la participación</h2><p>Las observaciones se conservan en orden cronológico.</p></div>
        @forelse($history as $entry)
            <article class="history-entry"><span @class(['history-dot', 'is-good' => $entry->calificacion === 1]) aria-hidden="true"></span><div class="history-content"><div class="history-top"><span @class(['complaint-status', 'is-good' => $entry->calificacion === 1, 'is-bad' => $entry->calificacion === 0])>{{ $entry->calificacion === 1 ? 'Favorable' : 'Incidencia' }}</span><small>{{ $entry->fecha ?: 'Sin fecha' }}</small></div><p>{{ $entry->observacion }}</p><div class="history-bottom"><small>Registró: {{ $entry->autor }} {{ $entry->origen === 'historico' ? '· Registro histórico' : '' }}</small>
                @if($entry->evidencia)
                    @php($evidenceUrl = route('quejas.evidence', [$entry->origen, $entry->id]))
                    @if(preg_match('/\.(pdf|jpe?g|png)$/i', $entry->evidencia_nombre ?: ''))
                        <button type="button" class="evidence-link" data-preview="{{ $evidenceUrl }}" data-filename="{{ $entry->evidencia_nombre }}">Ver evidencia ↗</button>
                    @else<a class="evidence-link" href="{{ $evidenceUrl }}">Descargar evidencia ↗</a>@endif
                @endif
            </div></div></article>
        @empty
            <div class="complaint-no-history"><strong>Aún no hay observaciones</strong><p>La primera valoración aparecerá aquí cuando la registres.</p></div>
        @endforelse
    </section>

    <aside class="panel complaint-compose" aria-labelledby="complaint-compose-title"><span class="eyebrow">Nuevo registro</span><h2 id="complaint-compose-title">Agregar observación</h2><p>La valoración quedará asociada únicamente a este permisionario y participación.</p>
        @if($errors->any())<div class="form-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <form method="post" action="{{ route('quejas.store', [$participation->origen, $participation->registro_id]) }}" enctype="multipart/form-data">@csrf
            <fieldset class="rating-choice"><legend>Valoración del servicio</legend><label><input type="radio" name="calificacion" value="1" @checked(old('calificacion') === '1') required><span><strong>Favorable</strong><small>Buen servicio o cumplimiento</small></span></label><label><input type="radio" name="calificacion" value="0" @checked(old('calificacion') === '0') required><span><strong>Incidencia</strong><small>Queja, adeudo u observación</small></span></label></fieldset>
            <label for="complaint-text">Observación o queja</label><textarea id="complaint-text" name="observacion" rows="6" maxlength="2000" required placeholder="Describe los hechos de forma clara y concreta..." data-character-input>{{ old('observacion') }}</textarea><small class="character-counter"><span data-character-count>0</span> / 2000 caracteres</small>
            <label for="complaint-file">Evidencia o sustento <span class="optional-label">Opcional</span></label><input id="complaint-file" type="file" name="evidencia" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"><p class="file-hint">PDF, imagen o Word. Hasta 10 MB.</p>
            <button class="button complaint-submit" type="submit">Guardar observación</button>
        </form>
    </aside>
</div>

<dialog class="evidence-dialog" data-evidence-dialog aria-label="Vista de evidencia"><div class="evidence-dialog-head"><strong data-evidence-title>Evidencia</strong><button type="button" aria-label="Cerrar vista previa" data-evidence-close>✕</button></div><iframe title="Vista previa de evidencia" data-evidence-frame></iframe><div class="evidence-dialog-foot"><span>Si tu dispositivo no muestra el archivo, ábrelo en otra pestaña.</span><a class="outline-button" data-evidence-open target="_blank" rel="noopener">Abrir archivo ↗</a></div></dialog>

@push('scripts')
<script>(() => { const input = document.querySelector('[data-character-input]'); const count = document.querySelector('[data-character-count]'); const update = () => { if (count && input) count.textContent = input.value.length; }; input?.addEventListener('input', update); update(); const dialog = document.querySelector('[data-evidence-dialog]'); const frame = dialog?.querySelector('[data-evidence-frame]'); const close = () => { dialog?.close(); if (frame) frame.src = 'about:blank'; }; document.querySelectorAll('[data-preview]').forEach(button => button.addEventListener('click', () => { const url = button.dataset.preview; dialog.querySelector('[data-evidence-title]').textContent = button.dataset.filename || 'Evidencia'; dialog.querySelector('[data-evidence-open]').href = url; frame.src = url; dialog.showModal(); })); dialog?.querySelector('[data-evidence-close]')?.addEventListener('click', close); dialog?.addEventListener('click', event => { if (event.target === dialog) close(); }); dialog?.addEventListener('close', () => { if (frame) frame.src = 'about:blank'; }); })();</script>
@endpush
@endsection
