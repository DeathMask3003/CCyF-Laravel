@extends('layouts.app')

@section('title', $convocation ? 'Editar convocatoria' : 'Agregar convocatoria')

@section('content')
<a class="back-link" href="{{ route('convocatorias.index') }}">← Número de convocatoria</a>
<div class="page-heading"><div><span class="eyebrow">{{ $convocation ? 'Convocatoria '.$convocation->id : 'Nuevo registro' }}</span><h1>{{ $convocation ? $convocation->numero : 'Agregar convocatoria' }}</h1><p>Define el número y el servicio. Los planteles participantes se administran en Enlaces para convocatoria.</p></div>@if ($convocation)<a class="outline-button button-link" href="{{ route('enlaces.edit', $convocation->id) }}">Administrar enlaces</a>@endif</div>
@if ($errors->any()) <div class="form-errors" role="alert"><strong>Revisa los datos:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif
<form method="post" action="{{ $convocation ? route('convocatorias.update', $convocation->id) : route('convocatorias.store') }}">@csrf @if ($convocation) @method('PUT') @endif
    <section class="panel"><div class="convocation-fields"><div><label for="conv-number">Número o nombre de convocatoria</label><input id="conv-number" name="numero" maxlength="100" value="{{ old('numero', $convocation?->numero) }}" placeholder="Ej. Quinta-2027-Cafetería" required></div><div><label for="conv-service">Tipo de servicio</label><select id="conv-service" name="servicio_id" required @disabled($hasCatalog)><option value="">Selecciona un servicio</option>@foreach ($services as $service)<option value="{{ $service->id }}" @selected((string) old('servicio_id', $convocation?->servicio_id) === (string) $service->id)>{{ $service->nombre }}</option>@endforeach</select>@if ($hasCatalog)<input type="hidden" name="servicio_id" value="{{ $convocation->servicio_id }}"><p class="field-help">El servicio queda fijo cuando ya existen productos configurados.</p>@endif</div></div></section>
    <div class="sticky-actions"><a class="outline-button button-link" href="{{ route('convocatorias.index') }}">Cancelar</a><button class="button" type="submit">{{ $convocation ? 'Guardar cambios' : 'Crear convocatoria' }}</button></div>
</form>
@endsection
