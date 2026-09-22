@extends('layouts.app')

@push('head')<link rel="stylesheet" href="{{ asset('css/documentacion.css') }}">@endpush
@section('title', 'Actualización de documentación')

@section('content')
<div class="page-heading doc-heading"><div><span class="eyebrow">CCyF · Expedientes</span><h1>Actualización de documentación</h1><p>Consulta y actualiza los archivos de cada participación. Los cambios quedan registrados.</p></div><span class="pill pill-neutral">{{ $totalCafe + $totalFoto }} {{ $totalCafe + $totalFoto === 1 ? 'expediente' : 'expedientes' }}</span></div>

<section class="panel doc-panel">
    <nav class="tracking-tabs" aria-label="Tipo de servicio">
        <a href="{{ route('documentacion.index', ['servicio'=>'cafeteria']) }}" @class(['active' => $service === 'cafeteria']) aria-current="{{ $service === 'cafeteria' ? 'page' : 'false' }}">☕ Cafetería <strong>{{ $totalCafe }}</strong></a>
        <a href="{{ route('documentacion.index', ['servicio'=>'fotocopiado']) }}" @class(['active' => $service === 'fotocopiado']) aria-current="{{ $service === 'fotocopiado' ? 'page' : 'false' }}">▤ Fotocopiado <strong>{{ $totalFoto }}</strong></a>
    </nav>
    <div class="doc-toolbar"><div><span class="eyebrow">{{ $isAdmin ? 'Gestión de permisionarios' : 'Mis participaciones' }}</span><h2>{{ $service === 'cafeteria' ? 'Cafetería' : 'Fotocopiado' }}</h2><p>{{ $rows->total() }} {{ $rows->total() === 1 ? 'resultado' : 'resultados' }}</p></div><form method="get" action="{{ route('documentacion.index') }}" class="doc-search"><input type="hidden" name="servicio" value="{{ $service }}"><label for="doc-search">Buscar expediente</label><input id="doc-search" type="search" name="buscar" maxlength="150" value="{{ request('buscar') }}" placeholder="Folio, nombre, plantel o CURP"><button class="button" type="submit">Buscar</button></form></div>
    <div class="doc-table-wrap"><table class="doc-table"><thead><tr><th># Doc</th><th>Nombre del permisionario</th><th>Correo electrónico</th><th>Participa para</th><th>Servicio</th><th>Teléfono</th><th>RFC</th><th>CURP</th><th>INE</th><th>Acciones</th></tr></thead><tbody>
        @forelse($rows as $row)
        <tr><td><strong>{{ $row->registration_id }}</strong><small>{{ $row->origin === 'historico' ? 'Histórico' : 'Nuevo' }}</small></td><td><strong>{{ $row->name ?: 'Sin nombre' }}</strong><small>{{ $row->convocation ?: 'Sin convocatoria' }}</small></td><td>{{ $row->email ?: '—' }}</td><td>{{ $row->campus ?: '—' }}</td><td>{{ $row->service_name }}</td><td>{{ $row->phone ?: '—' }}</td><td>{{ $row->rfc ?: '—' }}</td><td>{{ $row->curp ?: '—' }}</td><td>{{ $row->ine ?: '—' }}</td><td><a class="button button-link" href="{{ route('documentacion.show', $row->key) }}">Actualizar archivos</a></td></tr>
        @empty<tr><td colspan="10" class="doc-empty">No se encontraron expedientes de este servicio.</td></tr>@endforelse
    </tbody></table></div>
    @if($rows->hasPages())<nav class="pagination" aria-label="Páginas de expedientes">@if($rows->onFirstPage())<span>← Anterior</span>@else<a href="{{ $rows->previousPageUrl() }}">← Anterior</a>@endif<strong>Página {{ $rows->currentPage() }} de {{ $rows->lastPage() }}</strong>@if($rows->hasMorePages())<a href="{{ $rows->nextPageUrl() }}">Siguiente →</a>@else<span>Siguiente →</span>@endif</nav>@endif
</section>
@endsection
