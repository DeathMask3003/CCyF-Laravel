@if ($records->hasPages())
    <nav class="pagination" aria-label="Páginas de propuestas">
        @if ($records->onFirstPage())<span>Anterior</span>@else<a href="{{ $records->previousPageUrl() }}">← Anterior</a>@endif
        <strong>Página {{ $records->currentPage() }} de {{ $records->lastPage() }}</strong>
        @if ($records->hasMorePages())<a href="{{ $records->nextPageUrl() }}">Siguiente →</a>@else<span>Siguiente</span>@endif
    </nav>
@endif
