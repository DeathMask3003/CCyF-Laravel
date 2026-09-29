@extends('layouts.app')

@push('head')<link rel="stylesheet" href="{{ asset('css/complaints.css') }}?v=20260923-1">@endpush
@section('title', $complaint ? 'Editar queja manual' : 'Nueva queja manual')
@section('content')
<a class="back-link" href="{{ route('quejas.index', ['vista' => 'manuales']) }}">← Volver a quejas manuales</a>
<div class="complaint-heading"><div><span class="eyebrow">Observaciones y quejas · Registro manual</span><h1>{{ $complaint ? 'Editar queja manual' : 'Nueva queja manual' }}</h1><p>Registra incidencias que no estén ligadas a una participación digital.</p></div></div>
<div class="manual-form-layout"><section class="panel manual-form-panel">
    @if($errors->any())<div class="form-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="post" action="{{ $complaint ? route('quejas.manual.update', $complaint->id) : route('quejas.manual.store') }}">@csrf @if($complaint) @method('PUT') @endif
        <div class="manual-form-grid"><div><label for="manual-name">Nombre completo del permisionario</label><input id="manual-name" name="permisionario" value="{{ old('permisionario', $complaint?->permisionario) }}" maxlength="150" required autocomplete="name" placeholder="Nombre y apellidos"></div><div><label for="manual-campus">Plantel o CEMSAD</label><select id="manual-campus" name="plantel_id" required><option value="">Selecciona un plantel</option>@foreach($campuses as $campus)<option value="{{ $campus->id }}" @selected((int) old('plantel_id', $complaint?->plantel_id) === (int) $campus->id)>{{ $campus->nombre }}</option>@endforeach</select></div></div>
        <label for="manual-text">Queja o adeudo</label><textarea id="manual-text" name="queja" rows="8" maxlength="2000" required placeholder="Describe los hechos, el plantel y cualquier dato que facilite el seguimiento." data-character-input>{{ old('queja', $complaint?->queja) }}</textarea><small class="character-counter"><span data-character-count>0</span> / 2000 caracteres</small>
        <div class="manual-form-actions"><a class="outline-button" href="{{ route('quejas.index', ['vista' => 'manuales']) }}">Cancelar</a><button class="button" type="submit">{{ $complaint ? 'Guardar cambios' : 'Registrar queja' }}</button></div>
    </form>
</section><aside class="manual-help"><strong>Antes de registrar</strong><p>Verifica el nombre y el plantel. Escribe una descripción objetiva; las personas con acceso al módulo podrán consultarla.</p><p>Para una participación registrada, usa su historial y adjunta la evidencia ahí.</p></aside></div>
@push('scripts')<script>(() => { const input = document.querySelector('[data-character-input]'); const count = document.querySelector('[data-character-count]'); const update = () => { if (count && input) count.textContent = input.value.length; }; input?.addEventListener('input', update); update(); })();</script>@endpush
@endsection
