import * as pdfjs from '../vendor/pdfjs/pdf.min.mjs';

pdfjs.GlobalWorkerOptions.workerSrc = new URL('../vendor/pdfjs/pdf.worker.min.mjs', import.meta.url).href;

const dialog = document.getElementById('preval-dialog');
if (dialog) {
    const links = [...dialog.querySelectorAll('[data-preval-pdf]')];
    const canvas = document.getElementById('preval-pdf-canvas');
    const stage = document.getElementById('preval-pdf-stage');
    const empty = document.getElementById('preval-pdf-empty');
    const error = document.getElementById('preval-pdf-error');
    const open = document.getElementById('preval-pdf-open');
    const name = document.getElementById('preval-pdf-name');
    const count = document.getElementById('preval-page-count');
    const zoomLabel = document.getElementById('preval-zoom-label');
    let pdf = null;
    let pageNumber = 1;
    let zoom = 1;
    let requestId = 0;
    let renderTask = null;

    const updateControls = () => {
        count.textContent = `Página ${pageNumber} de ${pdf?.numPages ?? 1}`;
        zoomLabel.textContent = `${Math.round(zoom * 100)} %`;
        document.getElementById('preval-page-prev').disabled = !pdf || pageNumber <= 1;
        document.getElementById('preval-page-next').disabled = !pdf || pageNumber >= pdf.numPages;
    };

    async function render() {
        if (!pdf) return;
        if (renderTask) {
            renderTask.cancel();
            try { await renderTask.promise; } catch (_) { /* La página anterior fue cancelada. */ }
        }
        const source = pdf;
        const page = await source.getPage(pageNumber);
        if (source !== pdf) return;
        const base = page.getViewport({ scale: 1 });
        const availableWidth = Math.max(280, dialog.querySelector('.preval-viewer').clientWidth - 28);
        const cssScale = (availableWidth / base.width) * zoom;
        const viewport = page.getViewport({ scale: cssScale });
        const pixelRatio = Math.min(window.devicePixelRatio || 1, 2);
        canvas.width = Math.floor(viewport.width * pixelRatio);
        canvas.height = Math.floor(viewport.height * pixelRatio);
        canvas.style.width = `${Math.floor(viewport.width)}px`;
        canvas.style.height = `${Math.floor(viewport.height)}px`;
        const context = canvas.getContext('2d', { alpha: false });
        renderTask = page.render({ canvasContext: context, viewport, transform: pixelRatio === 1 ? null : [pixelRatio, 0, 0, pixelRatio, 0, 0] });
        try {
            await renderTask.promise;
            stage.hidden = false;
            error.hidden = true;
            updateControls();
        } catch (exception) {
            if (exception?.name !== 'RenderingCancelledException') throw exception;
        }
    }

    async function load(link) {
        const current = ++requestId;
        links.forEach(item => item.classList.toggle('selected', item === link));
        name.textContent = link.dataset.pdfName || 'Documento PDF';
        open.href = link.href;
        open.hidden = false;
        empty.hidden = true;
        error.hidden = true;
        stage.hidden = true;
        if (renderTask) renderTask.cancel();
        if (pdf) { await pdf.destroy(); pdf = null; }
        pageNumber = 1;
        zoom = 1;
        try {
            const task = pdfjs.getDocument({ url: link.href, cMapUrl: new URL('../vendor/pdfjs/cmaps/', import.meta.url).href,
                cMapPacked: true, standardFontDataUrl: new URL('../vendor/pdfjs/standard_fonts/', import.meta.url).href });
            const documentPdf = await task.promise;
            if (current !== requestId) { await documentPdf.destroy(); return; }
            pdf = documentPdf;
            await render();
        } catch (exception) {
            if (current !== requestId) return;
            stage.hidden = true;
            error.hidden = false;
            console.error('No se pudo mostrar el PDF en el modal:', exception);
        }
    }

    links.forEach(link => link.addEventListener('click', event => { event.preventDefault(); load(link); }));
    document.getElementById('preval-page-prev').addEventListener('click', () => { if (pdf && pageNumber > 1) { pageNumber--; render().catch(console.error); } });
    document.getElementById('preval-page-next').addEventListener('click', () => { if (pdf && pageNumber < pdf.numPages) { pageNumber++; render().catch(console.error); } });
    document.getElementById('preval-zoom-out').addEventListener('click', () => { zoom = Math.max(0.6, zoom - 0.2); render().catch(console.error); });
    document.getElementById('preval-zoom-in').addEventListener('click', () => { zoom = Math.min(2.2, zoom + 0.2); render().catch(console.error); });
    let resizeTimer;
    window.addEventListener('resize', () => { clearTimeout(resizeTimer); resizeTimer = setTimeout(() => render().catch(console.error), 180); });
    dialog.addEventListener('close', () => { if (renderTask) renderTask.cancel(); if (pdf) pdf.destroy(); pdf = null; });
    if (links.length) load(links[0]);
}
