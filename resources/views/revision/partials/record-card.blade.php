@php
    $isPending = $pending || $record->estado === 'Recibido';
    $isDesignated = in_array($record->decision ?? null, ['designado', 'Designado'], true);
    $isNotAccepted = ($record->decision ?? null) === 'no_aceptado';
    $stateLabel = $isPending ? 'Recibido · en revisión'
        : ($isDesignated ? 'Designado' : ($isNotAccepted ? 'No aceptado' : 'No designado'));
    $stateClass = $isPending ? 'review-card-state--pending'
        : ($isDesignated ? 'review-card-state--designated' : 'review-card-state--closed');
    $isHistorical = ($record->origen ?? 'actual') === 'historico';
    $date = $pending ? $record->enviado_at : ($record->finalizado_at ?: $record->enviado_at);
    $phone = trim((string) ($record->telefono ?? ''));
    $phoneDisplay = preg_match('/^521\d{10}$/', $phone)
        ? '+52 1 '.substr($phone, 3, 3).' '.substr($phone, 6, 3).' '.substr($phone, 9)
        : ($phone ?: 'No registrado');
    $serviceKind = match ((int) ($record->legacy_servicio_id ?? 0)) { 3 => 'cafe', 4 => 'foto', default => 'other' };
@endphp
<article class="review-card review-card--{{ $serviceKind }}" aria-label="Expediente {{ $record->folio }} de {{ $record->solicitante ?: 'participante' }}">
    <div class="review-card-top">
        <div class="review-card-id">
            @if ($pending)
                <label class="review-card-select" title="Seleccionar para finalizar como no aceptada">
                    <input type="checkbox" name="registros[]" value="{{ $record->id }}" form="bulk-review" aria-label="Seleccionar {{ $record->folio }}">
                    <span class="sr-only">Seleccionar {{ $record->folio }}</span>
                </label>
            @endif
            <div><span class="review-card-label">Folio de registro</span><strong>{{ $record->folio }}</strong></div>
        </div>
        <div class="review-card-badges">
            <span class="review-card-kind">{{ $record->servicio_nombre ?: 'Servicio' }}</span>
            @if ($isHistorical)<span class="review-card-origin">Registro histórico</span>@endif
            <span class="review-card-state {{ $stateClass }}">{{ $stateLabel }}</span>
        </div>
    </div>

    <div class="review-card-body">
        <div class="review-card-person">
            <span class="review-card-label">{{ $isContestant ? 'Titular del registro' : 'Permisionario' }}</span>
            <h2>{{ $record->solicitante ?: 'Participante sin nombre registrado' }}</h2>
            @if ($isHistorical && $record->folio_original !== $record->folio)
                <small>Referencia original: {{ $record->folio_original }}</small>
            @endif
        </div>

        <div class="review-card-focus" aria-label="Servicio y plantel de participación">
            <div class="review-card-service"><span>Participa para</span><strong>{{ $record->servicio_nombre ?: 'Servicio sin dato' }}</strong></div>
            <div class="review-card-campus"><span>Participa en</span><strong>{{ $record->plantel_nombre ?: 'Plantel sin dato' }}</strong></div>
        </div>

        <dl class="review-card-details">
            <div><dt>Convocatoria</dt><dd>{{ $record->convocatoria_nombre ?: 'Sin dato' }}</dd></div>
            <div><dt>Correo electrónico</dt><dd>{{ $record->correo ?: 'No registrado' }}</dd></div>
            <div><dt>Teléfono</dt><dd>{{ $phoneDisplay }}</dd></div>
            <div><dt>{{ $pending || $isPending ? 'Fecha de registro' : 'Fecha de conclusión' }}</dt><dd>{{ $date ? \Illuminate\Support\Carbon::parse($date)->format('d/m/Y · H:i') : 'Sin fecha registrada' }}</dd></div>
        </dl>
    </div>

    <div class="review-card-footer">
        <span class="review-card-footer-note">{{ $isPending ? 'Expediente en revisión' : 'Resolución registrada' }}</span>
        <div class="review-card-actions">
            <a class="outline-button button-link" href="{{ $isHistorical ? route('revision.historical', $record->id) : route('revision.show', $record->id) }}">{{ $pending ? 'Revisar expediente' : ($isPending ? 'Ver registro' : 'Ver resultado') }} <span aria-hidden="true">→</span></a>
            @if (! $pending && ($record->resultado_pdf_disponible ?? false))
                <a class="button button-link" href="{{ $isHistorical ? route('revision.historical-result-pdf', $record->id) : route('revision.result-letter-pdf', $record->id) }}" target="_blank" rel="noopener">{{ $isDesignated ? 'Carta de designación' : ($isNotAccepted ? 'Carta de no aceptación' : 'Carta de no designación') }} <span aria-hidden="true">↗</span></a>
            @endif
            @if (! $pending && ($record->evaluacion_pdf_disponible ?? false))
                <a class="outline-button button-link" href="{{ $isHistorical ? route('revision.historical-final-evaluation-pdf', $record->id) : route('revision.final-evaluation-pdf', $record->id) }}" target="_blank" rel="noopener">Evaluación PDF <span aria-hidden="true">↗</span></a>
            @endif
        </div>
    </div>
</article>
