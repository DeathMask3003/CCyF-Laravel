@if ($paginator->hasPages())
    <nav class="complaint-pager" role="navigation" aria-label="Páginas de observaciones y quejas">
        <p class="complaint-pager-summary">Mostrando <strong>{{ $paginator->firstItem() }}</strong>–<strong>{{ $paginator->lastItem() }}</strong> de <strong>{{ $paginator->total() }}</strong> resultados</p>
        <div class="complaint-pager-links">
            @if ($paginator->onFirstPage())
                <span class="complaint-pager-control is-disabled" aria-disabled="true">← Anterior</span>
            @else
                <a class="complaint-pager-control" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Página anterior">← Anterior</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="complaint-pager-ellipsis" aria-hidden="true">…</span>
                @else
                    @foreach ($element as $page => $url)
                        @if ($page === $paginator->currentPage())
                            <span class="complaint-pager-page is-current" aria-current="page" aria-label="Página {{ $page }}">{{ $page }}</span>
                        @else
                            <a class="complaint-pager-page" href="{{ $url }}" aria-label="Ir a la página {{ $page }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a class="complaint-pager-control" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Página siguiente">Siguiente →</a>
            @else
                <span class="complaint-pager-control is-disabled" aria-disabled="true">Siguiente →</span>
            @endif
        </div>
    </nav>
@endif
