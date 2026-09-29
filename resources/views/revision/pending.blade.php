@extends('layouts.app')

@section('title', 'Convocatorias pendientes')
@push('head')<link rel="stylesheet" href="{{ asset('css/review-cards.css') }}?v=20260927-2"><link rel="stylesheet" href="{{ asset('css/review-confirm.css') }}?v=20260927-2">@endpush
@section('content')
<div class="page-heading review-heading"><div><span class="eyebrow">Gestión de propuestas · CCyF</span><h1>Convocatorias pendientes</h1><p>Revisa el expediente y la oferta de cada concursante antes de registrar el resultado.</p></div><span class="pill pill-pending">{{ $records->total() }} por revisar</span></div>
<div class="review-stats"><div><strong>{{ $records->total() }}</strong><span>Resultados de los filtros</span></div><div><strong>{{ $summary->sum() }}</strong><span>Propuestas recibidas</span></div><div><strong>{{ $convocations->count() }}</strong><span>Convocatorias activas</span></div></div>
@include('revision.filters', ['action' => route('revision.pending')])
@if ($errors->any())<div class="form-errors" role="alert"><strong>Revisa la selección.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@if ($records->isEmpty())
    <div class="empty-state review-empty"><strong>No hay propuestas pendientes con estos filtros.</strong><p>Los registros enviados desde Nuevo registro aparecerán aquí para su revisión.</p></div>
@else
    <form id="bulk-review" method="post" action="{{ route('revision.finish-bulk') }}" class="review-bulk panel" data-review-confirm="bulk">@csrf
        <div class="review-bulk-intro">
            <strong>Finalizar y notificar seleccionados</strong>
            <p>Elige un resultado común. Cada expediente conservará su propia resolución y recibirá su carta por correo cuando el envío esté configurado.</p>
            <label class="review-bulk-select-all"><input type="checkbox" data-review-select-all> Seleccionar todos de esta página <span data-review-count>0 seleccionados</span></label>
        </div>
        <div class="review-bulk-fields">
            <label for="bulk-decision">Resultado
                <select id="bulk-decision" name="decision" required>
                    <option value="">Selecciona un resultado</option>
                    <option value="no_aceptado" @selected(old('decision') === 'no_aceptado')>No aceptado</option>
                    <option value="no_designado" @selected(old('decision') === 'no_designado')>No designado</option>
                </select>
            </label>
            <label for="bulk-answer">Respuesta para los expedientes
                <input id="bulk-answer" name="respuesta" maxlength="250" value="{{ old('respuesta') }}" placeholder="Motivo que recibirá cada participante" required>
            </label>
        </div>
        <button class="outline-button" type="submit">Finalizar y notificar</button>
        <p class="review-bulk-error" role="alert" data-review-bulk-error hidden>Selecciona al menos una propuesta antes de continuar.</p>
    </form>
    <div class="review-list review-cards">
        @foreach ($records as $record)
            @include('revision.partials.record-card', ['record' => $record, 'pending' => true, 'isContestant' => false])
        @endforeach
    </div>
    @include('revision.pagination')
    @include('revision.partials.confirm-dialog')
@endif
@endsection
@push('scripts')<script src="{{ asset('js/review-confirm.js') }}?v=20260927-2" defer></script>@endpush
