@extends('layouts.app')
@push('head')<link rel="stylesheet" href="{{ asset('css/contratos.css') }}">@endpush
@section('title', 'Contrato '.$row->folio)
@section('content')
<a class="back-link" href="{{ route('contratos.index', ['servicio'=>$row->service]) }}">← Volver a propuestas aceptadas</a>
<div class="page-heading contract-heading"><div><span class="eyebrow">{{ $row->service === 'cafeteria' ? 'Cafetería' : 'Fotocopiado' }} · {{ $row->convocation }}</span><h1>Contrato de {{ $row->name ?: 'permisionario' }}</h1><p>Expediente {{ $row->folio }} · {{ $row->campus }}</p></div><span @class(['contract-status', 'sent' => $row->sent, 'pending' => !$row->sent && $missing, 'ready' => !$row->sent && !$missing])>{{ $row->sent ? 'Enviado' : ($missing ? 'Requiere revisión' : 'Listo para revisión') }}</span></div>
@if($errors->any())<div class="form-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="contract-detail-layout">
    <section class="panel contract-detail-card"><span class="eyebrow">01 · Datos del contrato</span><h2>Información principal</h2><dl class="contract-data-grid">
        <div><dt>Permisionario</dt><dd>{{ $row->name ?: '—' }}</dd></div><div><dt>Servicio</dt><dd>{{ $row->service === 'cafeteria' ? 'Cafetería' : 'Fotocopiado' }}</dd></div>
        <div><dt>Plantel</dt><dd>{{ $row->campus ?: '—' }}</dd></div><div><dt>Convocatoria</dt><dd>{{ $row->convocation ?: '—' }}</dd></div>
        <div><dt>Dirección del plantel</dt><dd>{{ $row->campus_address ?: '—' }}</dd></div><div><dt>Monto mensual</dt><dd>{{ $row->amount !== null ? '$'.number_format((float)$row->amount,2).' MXN' : '—' }}</dd></div>
        <div><dt>Inicio de vigencia</dt><dd>{{ $row->starts ? \Carbon\Carbon::parse($row->starts)->format('d/m/Y') : '—' }}</dd></div><div><dt>Fin de vigencia</dt><dd>{{ $row->ends ? \Carbon\Carbon::parse($row->ends)->format('d/m/Y') : '—' }}</dd></div>
        <div><dt>Correo electrónico</dt><dd>{{ $row->email ?: '—' }}</dd></div><div><dt>Teléfono</dt><dd>{{ $row->phone ?: '—' }}</dd></div>
    </dl><a class="contract-context-link" href="{{ $row->origin === 'historico' ? route('revision.historical', $row->id) : route('revision.show', $row->id) }}">Consultar expediente y decisión original ↗</a></section>
    <aside class="contract-side"><section class="panel contract-check-card"><span class="eyebrow">02 · Revisión</span><h2>Antes de enviar</h2>
        @if($missing)<p>Atiende estos puntos antes de emitir el contrato:</p><ul class="contract-missing">@foreach($missing as $item)<li>{{ $item }}</li>@endforeach</ul><p class="contract-help">Revisa la plantilla y los datos del expediente correspondiente.</p>@else<p class="contract-ok">Los datos y la plantilla están completos.</p>@endif
        @if(!$template)<p class="contract-help">No hay plantilla para este servicio.</p>@else<p class="contract-help">Plantilla {{ $template->source === 'local' ? 'editada en CCyF' : 'original recuperada' }}.</p>@endif
        <a class="contract-context-link" href="{{ route('contratos.template', $row->service) }}">Revisar plantilla ↗</a>
    </section><section class="panel contract-send-card"><span class="eyebrow">03 · Entrega</span><h2>Contrato por correo</h2>
        @if($row->sent)<p>Figura como enviado{{ $row->sent_at ? ' el '.\Carbon\Carbon::parse($row->sent_at)->format('d/m/Y H:i') : ' en el sistema anterior' }}.</p>@if($row->delivery?->sent_at)<p>Destinatario: <strong>{{ $row->delivery->recipient }}</strong></p><a class="contract-outline" href="{{ route('contratos.sent-pdf', $row->key) }}" target="_blank" rel="noopener">Ver PDF enviado ↗</a>@endif
        @elseif($row->delivery)<p>Un intento de envío requiere revisión administrativa. Se bloqueó el reenvío automático para evitar duplicados.</p>
        @elseif(!$mailReady)<p>El correo de esta copia sigue en modo de prueba. El envío se habilitará al configurar un remitente real.</p>
        @elseif($missing || !$template)<p>Completa los datos y la plantilla antes de enviar.</p>
        @else<form method="post" action="{{ route('contratos.send', $row->key) }}" onsubmit="return confirm('¿Enviar el contrato a {{ $row->email }}?')">@csrf<button class="button contract-send-full" type="submit">Enviar contrato a {{ $row->email }}</button></form>@endif
    </section></aside>
</div>
<section class="panel contract-terms-card"><details @if($row->amount === null || !$row->starts || !$row->ends) open @endif><summary><span><span class="eyebrow">Datos contractuales</span><strong>Ajustar monto, vigencia y contacto</strong><small>Los ajustes se guardan en CCyF; el expediente histórico se conserva.</small></span><span aria-hidden="true">⌄</span></summary>
    @if($row->sent || $row->delivery)<p>Los datos quedaron cerrados tras el intento de envío.</p>@else
    <form method="post" action="{{ route('contratos.terms', $row->key) }}">@csrf @method('PUT')<div class="contract-terms-grid">
        <div><label for="term-amount">Aportación mensual (MXN)</label><input id="term-amount" type="number" name="amount" step="0.01" min="0.01" max="9999999999.99" value="{{ old('amount', $row->amount) }}"></div>
        <div><label for="term-starts">Inicio de vigencia</label><input id="term-starts" type="date" name="starts" value="{{ old('starts', $row->starts) }}"></div>
        <div><label for="term-ends">Fin de vigencia</label><input id="term-ends" type="date" name="ends" value="{{ old('ends', $row->ends) }}"></div>
        <div class="wide"><label for="term-campus">Dirección del plantel</label><input id="term-campus" name="campus_address" maxlength="500" value="{{ old('campus_address', $row->campus_address) }}"></div>
        <div><label for="term-email">Correo del destinatario</label><input id="term-email" type="email" name="email" maxlength="200" value="{{ old('email', $row->email) }}"></div>
        <div><label for="term-phone">Teléfono</label><input id="term-phone" name="phone" maxlength="40" value="{{ old('phone', $row->phone) }}"></div>
        <div class="wide"><label for="term-address">Domicilio del permisionario</label><input id="term-address" name="address" maxlength="500" value="{{ old('address', $row->address) }}"></div>
        <div><label for="term-ine">Clave de elector (INE)</label><input id="term-ine" name="ine" maxlength="40" value="{{ old('ine', $row->ine) }}"></div>
        <div><label for="term-alternate">Contacto alterno</label><input id="term-alternate" name="alternate" maxlength="180" value="{{ old('alternate', $row->alternate) }}"></div>
        <div><label for="term-alternate-phone">Teléfono alterno</label><input id="term-alternate-phone" name="alternate_phone" maxlength="40" value="{{ old('alternate_phone', $row->alternate_phone) }}"></div>
    </div><div class="contract-terms-actions"><p>Confirma cada dato con el expediente. Una nueva versión sustituirá estos valores solo en el contrato.</p><button class="button" type="submit">Guardar ajustes</button></div></form>@endif
    @if($termHistory->isNotEmpty())<div class="contract-term-history"><h3>Historial de ajustes</h3>@foreach($termHistory as $version)<details><summary>Versión #{{ $version->id }} · {{ $version->editor ?: 'Usuario' }} · {{ \Carbon\Carbon::parse($version->created_at)->format('d/m/Y H:i') }}</summary><p>Monto: {{ $version->amount !== null ? '$'.number_format((float)$version->amount,2) : '—' }} · Vigencia: {{ $version->starts ?: '—' }} a {{ $version->ends ?: '—' }} · Correo: {{ $version->email ?: '—' }}</p></details>@endforeach</div>@endif
</details></section>
<section class="panel contract-preview-card"><div class="contract-preview-head"><div><span class="eyebrow">Vista previa</span><h2>Borrador PDF</h2><p>Revisa el texto y los datos antes de enviarlo. El borrador lleva una marca visible.</p></div>@if($template)<a class="contract-outline" href="{{ route('contratos.preview', $row->key) }}" target="_blank" rel="noopener">Abrir PDF en otra pestaña ↗</a>@endif</div>
    @if($template)<div class="contract-pdf-shell"><button type="button" class="button" id="contract-load-pdf" data-url="{{ route('contratos.preview', $row->key) }}">Cargar vista previa aquí</button><iframe id="contract-pdf-frame" title="Borrador del contrato" hidden></iframe></div><p class="contract-mobile-tip">En iPad o teléfono, usa “Abrir PDF en otra pestaña” para verlo a pantalla completa.</p>@else<div class="contract-empty">Configura una plantilla para generar el borrador.</div>@endif
</section>
@push('scripts')<script>document.getElementById('contract-load-pdf')?.addEventListener('click',function(){const frame=document.getElementById('contract-pdf-frame');frame.src=this.dataset.url;frame.hidden=false;this.hidden=true;});</script>@endpush
@endsection
