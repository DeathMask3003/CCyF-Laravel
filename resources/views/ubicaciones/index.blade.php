@extends('layouts.app')

@section('title', 'Mis ubicaciones')
@push('head')<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">@endpush

@section('content')
<div class="page-heading profile-heading">
    <div><span class="eyebrow">Actividad personal</span><h1>Mis ubicaciones</h1><p>Consulta el historial de ubicaciones asociado a tu cuenta.</p></div>
    <a class="outline-button button-link" href="{{ route('perfil.show') }}">Volver a mi perfil</a>
</div>

<section class="location-hero panel">
    <div><span class="eyebrow">Historial privado</span><h2>{{ number_format($total) }} ubicaciones registradas</h2><p>Los registros anteriores conservan su fuente original. Para agregar una ubicación nueva, usa el botón y permite el acceso en tu navegador.</p></div>
    <div class="location-action"><button id="location-capture" type="button" class="button" data-url="{{ route('ubicaciones.store') }}" data-token="{{ csrf_token() }}">Guardar ubicación actual</button><span id="location-status" role="status" aria-live="polite"></span></div>
</section>

<div class="location-toolbar"><div><span class="pill pill-ready">Precisa</span> <span class="pill pill-pending">Aproximada</span></div><div class="location-toolbar-actions"><button id="show-location-map" type="button" class="outline-button">Mostrar mapa</button><a class="outline-button button-link" href="{{ $today ? route('ubicaciones.index') : route('ubicaciones.index', ['hoy' => 1]) }}">{{ $today ? 'Ver todo el historial' : 'Mostrar solo hoy' }}</a></div></div>
<section id="location-map-panel" class="panel location-map-panel" hidden aria-label="Mapa de ubicaciones"><div class="section-bar"><div><span class="eyebrow">Vista geográfica</span><h2>Ubicaciones de esta página</h2></div><span class="muted">Mapa de OpenStreetMap</span></div><div id="location-map" role="img" aria-label="Mapa con las ubicaciones registradas en esta página"></div><p class="field-help">Al mostrar el mapa se cargan imágenes de OpenStreetMap. Cada registro también tiene un enlace individual al mapa.</p></section>

<section class="panel location-list-panel" aria-labelledby="history-title">
    <div class="section-bar"><h2 id="history-title">Historial {{ $today ? 'de hoy' : 'reciente' }}</h2><span class="muted">{{ $locations->total() }} resultados</span></div>
    @forelse($locations as $location)
        @if($loop->first)<div class="location-list">@endif
        <article class="location-entry">
            <div class="location-marker" aria-hidden="true">⌖</div>
            <div class="location-main"><strong>{{ \Illuminate\Support\Carbon::parse($location->fecha_registro)->format('d/m/Y · H:i') }}</strong><span>{{ collect([$location->ciudad, $location->region, $location->pais])->filter()->implode(', ') ?: 'Ubicación sin ciudad registrada' }}</span><small>{{ $location->fuente ?: 'Registro histórico' }} @if($location->ip) · IP {{ $location->ip }} @endif</small></div>
            <div class="location-coordinates"><code>{{ number_format((float) $location->latitud, 6, '.', '') }}, {{ number_format((float) $location->longitud, 6, '.', '') }}</code><span>@if($location->precision_gps) Precisión ±{{ number_format((float) $location->precision_gps, 0) }} m @else Precisión no registrada @endif</span></div>
            <div class="location-links"><span class="pill {{ $location->es_aproximada ? 'pill-pending' : 'pill-ready' }}">{{ $location->es_aproximada ? 'Aproximada' : 'Precisa' }}</span><a href="https://www.google.com/maps?q={{ rawurlencode((string) $location->latitud.','.(string) $location->longitud) }}" target="_blank" rel="noopener noreferrer" class="text-link">Ver mapa ↗</a></div>
        </article>
        @if($loop->last)</div>@endif
    @empty
        <div class="empty-state">{{ $today ? 'No tienes ubicaciones registradas hoy.' : 'Aún no tienes ubicaciones registradas.' }}</div>
    @endforelse
    @if($locations->hasPages())
        <nav class="pagination" aria-label="Páginas de ubicaciones">
            @if($locations->onFirstPage())<span>← Anterior</span>@else<a href="{{ $locations->previousPageUrl() }}">← Anterior</a>@endif
            <strong>Página {{ $locations->currentPage() }} de {{ $locations->lastPage() }}</strong>
            @if($locations->hasMorePages())<a href="{{ $locations->nextPageUrl() }}">Siguiente →</a>@else<span>Siguiente →</span>@endif
        </nav>
    @endif
</section>
@push('scripts')
<script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
<script>
document.getElementById('show-location-map').addEventListener('click', function () {
    const points = {{ \Illuminate\Support\Js::from($mapPoints) }};
    const panel = document.getElementById('location-map-panel');
    panel.hidden = false; this.hidden = true;
    if (!points.length) { document.getElementById('location-map').textContent = 'No hay ubicaciones para mostrar en el mapa.'; return; }
    const map = L.map('location-map', {scrollWheelZoom:false});
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom:18, attribution:'&copy; OpenStreetMap contributors'}).addTo(map);
    const bounds = [];
    points.forEach((point, index) => {
        if (!Number.isFinite(point.lat) || !Number.isFinite(point.lng)) return;
        const marker = L.marker([point.lat, point.lng]).addTo(map);
        const detail = document.createElement('div');
        const title = document.createElement('strong'); title.textContent = point.city;
        const date = document.createElement('small'); date.textContent = point.date || '';
        detail.append(title, document.createElement('br'), date);
        marker.bindPopup(detail);
        if (index === 0 && point.accuracy) L.circle([point.lat, point.lng], {radius:point.accuracy, color:'#722344', fillOpacity:.08}).addTo(map);
        bounds.push([point.lat, point.lng]);
    });
    if (bounds.length) map.fitBounds(bounds, {padding:[28,28], maxZoom:14});
    setTimeout(() => map.invalidateSize(), 0);
});
document.getElementById('location-capture').addEventListener('click', function () {
    const button = this, status = document.getElementById('location-status');
    if (!navigator.geolocation) { status.textContent = 'Tu navegador no permite obtener la ubicación.'; return; }
    button.disabled = true; status.textContent = 'Obteniendo ubicación…';
    navigator.geolocation.getCurrentPosition(async position => {
        try {
            const response = await fetch(button.dataset.url, {method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':button.dataset.token}, credentials:'same-origin', body:JSON.stringify({latitud:position.coords.latitude,longitud:position.coords.longitude,precision_gps:position.coords.accuracy})});
            if (!response.ok) throw new Error('No se pudo guardar la ubicación.');
            window.location.reload();
        } catch (error) { status.textContent = error.message; button.disabled = false; }
    }, error => { status.textContent = error.code === 1 ? 'Permiso de ubicación denegado.' : 'No se pudo obtener la ubicación. Intenta de nuevo.'; button.disabled = false; }, {enableHighAccuracy:true, timeout:20000, maximumAge:0});
});
</script>
@endpush
@endsection
