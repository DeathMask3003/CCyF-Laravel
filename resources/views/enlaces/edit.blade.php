@extends('layouts.app')

@section('title', 'Editar enlaces')

@section('content')
<a class="back-link" href="{{ route('enlaces.index') }}">← Enlaces para convocatoria</a>
<div class="page-heading"><div><span class="eyebrow">{{ $convocation->servicio_nombre ?: 'Servicio por asignar' }}</span><h1>{{ $convocation->numero }}</h1><p>Selecciona los planteles que podrán participar en esta convocatoria.</p></div><span class="pill {{ $convocation->activo ? 'pill-ready' : 'pill-pending' }}">{{ $convocation->activo ? 'Activa' : 'Inactiva' }}</span></div>
@if ($errors->any()) <div class="form-errors" role="alert"><strong>Revisa los enlaces:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif
<form method="post" action="{{ route('enlaces.update', $convocation->id) }}">@csrf @method('PUT')
    <section class="panel"><div class="section-bar"><div><span class="eyebrow">Planteles disponibles</span><h2>Participación autorizada</h2><p class="muted">Usa el buscador para localizar planteles. Debe quedar al menos uno seleccionado.</p></div><span class="pill pill-neutral"><span id="selected-count">{{ count(old('planteles', $selected)) }}</span> seleccionados</span></div>
        <div class="picker-toolbar"><label class="sr-only" for="link-search">Buscar plantel</label><input id="link-search" type="search" placeholder="Buscar plantel o CEMSaD"><button class="quiet-button" id="select-visible" type="button">Seleccionar visibles</button><button class="quiet-button" id="clear-selection" type="button">Limpiar selección</button></div>
        <div class="campus-picker" id="campus-picker">@foreach ($campuses as $campus)<label data-campus="{{ mb_strtolower($campus->nombre) }}"><input type="checkbox" name="planteles[]" value="{{ $campus->id }}" @checked(in_array($campus->id, array_map('intval', old('planteles', $selected)), true))><span>{{ $campus->nombre }}</span></label>@endforeach</div>
        <p class="field-help" id="no-campus-results" hidden>No hay planteles que coincidan con la búsqueda.</p>
    </section>
    <div class="sticky-actions"><a class="outline-button button-link" href="{{ route('enlaces.index') }}">Cancelar</a><button class="button" type="submit">Guardar enlaces</button></div>
</form>
<script>
const search=document.getElementById('link-search'), labels=[...document.querySelectorAll('#campus-picker label')], count=document.getElementById('selected-count'), empty=document.getElementById('no-campus-results');
function refresh(){count.textContent=document.querySelectorAll('#campus-picker input:checked').length;}
function filter(){const q=search.value.trim().toLocaleLowerCase('es');let visible=0;labels.forEach(label=>{const show=label.dataset.campus.includes(q);label.hidden=!show;if(show)visible++;});empty.hidden=visible>0;}
labels.forEach(label=>label.querySelector('input').addEventListener('change',refresh));search.addEventListener('input',filter);
document.getElementById('select-visible').addEventListener('click',()=>{labels.filter(label=>!label.hidden).forEach(label=>label.querySelector('input').checked=true);refresh();});
document.getElementById('clear-selection').addEventListener('click',()=>{labels.forEach(label=>label.querySelector('input').checked=false);refresh();});
</script>
@endsection
