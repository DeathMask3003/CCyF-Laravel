@extends('layouts.app')

@push('head')<link rel="stylesheet" href="{{ asset('css/prevaluation-assignments.css') }}?v=20260923-3">@endpush

@section('title', 'Asignación de planteles')
@section('content')
<a class="back-link" href="{{ route('prevaluaciones.index', ['servicio' => $service]) }}">← Volver a prevaluaciones</a>

<div class="page-heading assignment-heading">
    <div><span class="eyebrow">Administración · Prevaluaciones</span><h1>Asignación de planteles</h1><p>Organiza la revisión por plantel o CEMSAD para la convocatoria vigente.</p></div>
    <span class="pill pill-neutral">{{ $assignmentRows->count() }} planteles</span>
</div>

<nav class="assignment-tabs" aria-label="Servicio a configurar">
    <a href="{{ route('prevaluaciones.assignments', ['servicio' => 'cafeteria']) }}" @class(['active' => $service === 'cafeteria']) @if($service === 'cafeteria') aria-current="page" @endif>Cafetería</a>
    <a href="{{ route('prevaluaciones.assignments', ['servicio' => 'fotocopiado']) }}" @class(['active' => $service === 'fotocopiado']) @if($service === 'fotocopiado') aria-current="page" @endif>Fotocopiado</a>
</nav>

@if($current)
    <div class="assignment-context"><span class="eyebrow">Convocatoria vigente</span><strong>{{ $current->numero }}</strong><p>Las asignaciones de esta convocatoria no afectan a las siguientes.</p></div>
    <div class="assignment-stats" aria-label="Resumen de asignaciones">
        <div><span>Planteles asignados</span><strong>{{ $assignedCount }} <small>/ {{ $assignmentRows->count() }}</small></strong></div>
        <div><span>Expedientes pendientes</span><strong>{{ $pendingCount }}</strong></div>
        <div><span>En revisión</span><strong>{{ $inProgressCount }}</strong></div>
    </div>

    @if($errors->any())<div class="form-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="panel assignment-panel" aria-labelledby="assignment-list-title">
        <div class="assignment-toolbar"><div><span class="eyebrow">Distribución de trabajo</span><h2 id="assignment-list-title">Planteles y prevaluadores</h2><p>Asigna un responsable a cada plantel. Los no asignados permanecen visibles para administración.</p></div><label for="assignment-search">Buscar plantel<input id="assignment-search" type="search" placeholder="Nombre o CEMSAD" autocomplete="off" data-assignment-search></label></div>
        @if($evaluators->isEmpty())
            <div class="assignment-empty">No hay usuarios activos con permiso de prevaluación. Configúralos en Gestión de roles.</div>
        @elseif($assignmentRows->isEmpty())
            <div class="assignment-empty">No hay planteles asociados a la convocatoria vigente de este servicio.</div>
        @else
            <form method="post" action="{{ route('prevaluaciones.assign-bulk') }}" data-assignment-form>@csrf
                <input type="hidden" name="servicio" value="{{ $service }}">
                <input type="hidden" name="convocatoria_id" value="{{ $current->id ?: $current->legacy_cat_id }}">
                <div class="assignment-list-header" aria-hidden="true"><span>Plantel o CEMSAD</span><span>Carga actual</span><span>Prevaluador responsable</span></div>
                <div class="assignment-list">
                @foreach($assignmentRows as $campus)
                    <div class="assignment-item" data-assignment-row data-campus-name="{{ $campus->name }}">
                        <input type="hidden" name="assignments[{{ $loop->index }}][plantel]" value="{{ $campus->name }}">
                        <div class="assignment-campus"><span class="assignment-campus-icon" aria-hidden="true">{{ str_contains(mb_strtolower($campus->name), 'cemsad') ? 'C' : 'P' }}</span><div><strong>{{ $campus->name }}</strong><small>{{ $campus->evaluator_id ? 'Responsable asignado' : 'Pendiente de asignar' }}</small></div></div>
                        <div class="assignment-load"><strong>{{ $campus->pending }}</strong><span>pendientes</span>@if($campus->inProgress)<small>{{ $campus->inProgress }} en revisión</small>@endif</div>
                        <label for="assignment-evaluator-{{ $loop->iteration }}"><span class="sr-only">Prevaluador para {{ $campus->name }}</span><select id="assignment-evaluator-{{ $loop->iteration }}" name="assignments[{{ $loop->index }}][evaluador_id]" data-original="{{ (int) $campus->evaluator_id }}"><option value="">Sin asignar</option>@foreach($evaluators as $evaluator)<option value="{{ $evaluator->getKey() }}" @selected((int) $campus->evaluator_id === (int) $evaluator->getKey())>{{ $evaluator->usu_area }}</option>@endforeach</select></label>
                    </div>
                @endforeach
                </div>
                <p class="assignment-empty" data-assignment-empty hidden>No hay planteles que coincidan con la búsqueda.</p>
                <div class="assignment-save-bar"><p><strong data-change-count>Sin cambios pendientes</strong><span>Se guardarán las asignaciones de todos los planteles, incluidos los ocultos por la búsqueda.</span></p><button class="primary-button" type="submit">Guardar todas las asignaciones</button></div>
            </form>
        @endif
    </section>
    <div class="assignment-guidance"><strong>Asignaciones seguras</strong><p>Un plantel se asigna a un prevaluador por convocatoria. Para cambiarlo, primero deben concluirse o liberarse sus expedientes en revisión. La última asignación no puede retirarse para evitar que la lista vuelva a quedar abierta.</p></div>
@else
    <div class="empty-state">Aún no hay una convocatoria disponible para este servicio.</div>
@endif

@push('scripts')
<script>
(() => {
    const search = document.querySelector('[data-assignment-search]');
    if (!search) return;
    const rows = [...document.querySelectorAll('[data-assignment-row]')];
    const empty = document.querySelector('[data-assignment-empty]');
    const selects = [...document.querySelectorAll('[data-assignment-form] select')];
    const changeCount = document.querySelector('[data-change-count]');
    const normalize = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es').trim();
    search.addEventListener('input', () => {
        const term = normalize(search.value);
        let shown = 0;
        rows.forEach(row => {
            row.hidden = !normalize(row.dataset.campusName).includes(term);
            if (!row.hidden) shown++;
        });
        if (empty) empty.hidden = shown !== 0;
    });
    const updateCount = () => {
        const count = selects.filter(select => Number(select.value || 0) !== Number(select.dataset.original || 0)).length;
        if (changeCount) changeCount.textContent = count ? `${count} ${count === 1 ? 'cambio pendiente' : 'cambios pendientes'}` : 'Sin cambios pendientes';
    };
    selects.forEach(select => select.addEventListener('change', updateCount));
})();
</script>
@endpush
@endsection
