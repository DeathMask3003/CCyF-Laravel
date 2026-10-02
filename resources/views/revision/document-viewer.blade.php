@push('head')<link rel="stylesheet" href="{{ asset('css/revision-viewer.css') }}?v=20261002-1">@endpush
<dialog id="review-document-dialog" class="review-document-dialog" aria-labelledby="review-document-title">
    <div class="review-document-shell">
        <header class="review-document-header">
            <div><span>{{ $viewerEyebrow ?? 'Documentación del permisionario' }}</span><h2 id="review-document-title">Documento</h2></div>
            <div class="review-document-header-actions"><a id="review-document-open" href="#" target="_blank" rel="noopener">Abrir archivo ↗</a><button type="button" id="review-document-close" aria-label="Cerrar documento">×</button></div>
        </header>
        <div id="review-document-message" class="review-document-message" role="status">Preparando documento…</div>
        <div id="review-document-scroll" class="review-document-scroll" hidden>
            <div id="review-document-pages" class="review-document-pages"></div>
        </div>
        <div id="review-document-image-wrap" class="review-document-image-wrap" hidden><img id="review-document-image" alt=""></div>
        <footer id="review-document-toolbar" class="review-document-toolbar" hidden>
            <button type="button" id="review-document-prev" aria-label="Página anterior">←</button>
            <span id="review-document-page-count">Página 1 de 1</span>
            <button type="button" id="review-document-next" aria-label="Página siguiente">→</button>
            <span class="review-document-divider"></span>
            <button type="button" id="review-document-zoom-out" aria-label="Reducir tamaño">−</button>
            <span id="review-document-zoom">100 %</span>
            <button type="button" id="review-document-zoom-in" aria-label="Aumentar tamaño">+</button>
        </footer>
    </div>
</dialog>
@push('scripts')<script type="module" src="{{ asset('js/revision-viewer.js') }}?v=20261002-1"></script>@endpush
