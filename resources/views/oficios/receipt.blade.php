@extends('layouts.app')

@section('title', 'Registro recibido')

@section('content')
<div class="receipt-wrap"><div class="receipt-check" aria-hidden="true">✓</div><span class="eyebrow">Registro recibido correctamente</span><h1>{{ $registration->folio }}</h1><p class="receipt-lead">Tu propuesta quedó registrada y lista para revisión.</p>
    <section class="panel receipt-card"><div><span>Convocatoria</span><strong>{{ $registration->convocatoria_nombre }}</strong></div><div><span>Servicio</span><strong>{{ $registration->servicio_nombre }}</strong></div><div><span>Plantel</span><strong>{{ $registration->plantel_nombre }}</strong></div><div><span>Tipo de invitación</span><strong>{{ $registration->tipo_nombre }}</strong></div><div><span>Documentos</span><strong>{{ $files }} PDF recibidos</strong></div><div><span>Estado</span><strong class="status-received">{{ $registration->estado }}</strong></div></section>
    <p class="receipt-date">Enviado el {{ \Illuminate\Support\Carbon::parse($registration->enviado_at)->translatedFormat('d \d\e F \d\e Y, H:i') }}</p><div class="receipt-actions"><a class="button button-link" href="{{ route('revision.finished') }}">Ver mis registros</a><a class="outline-button button-link" href="{{ route('oficios.index') }}">Volver a convocatorias</a></div>
</div>
@endsection
