@push('head')<link rel="stylesheet" href="{{ asset('css/review-filters.css') }}?v=20260923-1">@endpush
<form method="get" action="{{ $action }}" class="review-filters panel" aria-label="Filtrar propuestas" data-auto-filter>
    <label>Buscar por folio, participante o plantel
        <input type="search" name="buscar" value="{{ request('buscar') }}" placeholder="Escribe un dato para buscar" data-filter-search>
    </label>
    <label>Servicio
        <select name="servicio"><option value="">Todos los servicios</option>
            @foreach ($services as $service)<option value="{{ $service->id }}" @selected((string) request('servicio') === (string) $service->id)>{{ $service->nombre }}</option>@endforeach
        </select>
    </label>
    <label>Convocatoria
        <select name="convocatoria"><option value="">{{ $activeConvocationsOnly ?? false ? 'Filtrar por convocatoria activa' : 'Todas las convocatorias' }}</option>
            @foreach ($convocations as $convocation)<option value="{{ $convocation->id }}" @selected((string) request('convocatoria') === (string) $convocation->id)>{{ $convocation->numero }}</option>@endforeach
        </select>
    </label>
    <button class="button" type="submit" data-filter-submit>Aplicar filtros</button>
    @if (request()->hasAny(['buscar', 'servicio', 'convocatoria']))<a class="review-clear" href="{{ $action }}">Limpiar</a>@endif
</form>
@push('scripts')<script src="{{ asset('js/review-filters.js') }}?v=20260923-1" defer></script>@endpush
