import * as pdfjs from '../vendor/pdfjs/pdf.min.mjs';

pdfjs.GlobalWorkerOptions.workerSrc = new URL('../vendor/pdfjs/pdf.worker.min.mjs', import.meta.url).href;

const viewer = document.getElementById('contract-pdf-viewer');

if (viewer) {
    const scroller = document.getElementById('contract-pdf-scroll');
    const pages = document.getElementById('contract-pdf-pages');
    const count = document.getElementById('contract-pdf-count');
    const zoomLabel = document.getElementById('contract-pdf-zoom');
    const previous = document.getElementById('contract-pdf-prev');
    const next = document.getElementById('contract-pdf-next');
    const zoomOut = document.getElementById('contract-pdf-zoom-out');
    const zoomIn = document.getElementById('contract-pdf-zoom-in');
    let pdf = null;
    let slots = [];
    let observer = null;
    let currentPage = 1;
    let zoom = 1;
    let version = 0;
    let resizeTimer;
    const tasks = new Set();

    function updateControls() {
        count.textContent = `Página ${currentPage} de ${pdf?.numPages ?? 1}`;
        zoomLabel.textContent = `${Math.round(zoom * 100)} %`;
        previous.disabled = !pdf || currentPage <= 1;
        next.disabled = !pdf || currentPage >= pdf.numPages;
        zoomOut.disabled = !pdf || zoom <= 0.6;
        zoomIn.disabled = !pdf || zoom >= 2.2;
    }

    function clearPages() {
        version++;
        observer?.disconnect();
        observer = null;
        tasks.forEach(task => task.cancel());
        tasks.clear();
        slots = [];
        pages.replaceChildren();
    }

    function goToPage(number) {
        const slot = slots[number - 1];
        if (!slot) return;
        currentPage = number;
        updateControls();
        scroller.scrollTop = slot.getBoundingClientRect().top - scroller.getBoundingClientRect().top + scroller.scrollTop;
    }

    async function renderPage(number, expectedVersion) {
        const slot = slots[number - 1];
        if (!slot || slot.dataset.state !== 'pending' || !pdf) return;
        slot.dataset.state = 'rendering';
        const source = pdf;
        let task;
        try {
            const page = await source.getPage(number);
            if (expectedVersion !== version || source !== pdf) return;
            const base = page.getViewport({ scale: 1 });
            const width = Math.max(260, scroller.clientWidth - 34) * zoom;
            const viewport = page.getViewport({ scale: width / base.width });
            const pixelRatio = Math.min(window.devicePixelRatio || 1, 1.5);
            const canvas = document.createElement('canvas');
            canvas.width = Math.floor(viewport.width * pixelRatio);
            canvas.height = Math.floor(viewport.height * pixelRatio);
            canvas.style.width = `${Math.floor(viewport.width)}px`;
            canvas.style.height = `${Math.floor(viewport.height)}px`;
            canvas.setAttribute('role', 'img');
            canvas.setAttribute('aria-label', `Página ${number} de ${source.numPages}`);
            task = page.render({
                canvasContext: canvas.getContext('2d', { alpha: false }), viewport,
                transform: pixelRatio === 1 ? null : [pixelRatio, 0, 0, pixelRatio, 0, 0],
            });
            tasks.add(task);
            await task.promise;
            if (expectedVersion !== version || source !== pdf) return;
            slot.style.width = `${Math.floor(viewport.width)}px`;
            slot.style.minHeight = `${Math.floor(viewport.height)}px`;
            slot.replaceChildren(canvas);
            slot.dataset.state = 'done';
            page.cleanup();
        } catch (error) {
            if (expectedVersion !== version || error?.name === 'RenderingCancelledException') return;
            slot.dataset.state = 'error';
            slot.textContent = `No fue posible mostrar la página ${number}. Abre el PDF en otra pestaña.`;
            console.error('No se pudo dibujar una página del contrato:', error);
        } finally {
            if (task) tasks.delete(task);
        }
    }

    async function buildPages(focus = 1) {
        clearPages();
        if (!pdf) return;
        const expectedVersion = version;
        const source = pdf;
        const first = await source.getPage(1);
        if (expectedVersion !== version || source !== pdf) return;
        const base = first.getViewport({ scale: 1 });
        const width = Math.max(260, scroller.clientWidth - 34) * zoom;
        const height = width * base.height / base.width;
        const fragment = document.createDocumentFragment();
        slots = Array.from({ length: source.numPages }, (_, index) => {
            const slot = document.createElement('div');
            slot.className = 'contract-pdf-page';
            slot.dataset.page = String(index + 1);
            slot.dataset.state = 'pending';
            slot.style.width = `${Math.floor(width)}px`;
            slot.style.minHeight = `${Math.floor(height)}px`;
            slot.setAttribute('role', 'group');
            slot.setAttribute('aria-label', `Página ${index + 1} de ${source.numPages}`);
            slot.textContent = `Preparando página ${index + 1}…`;
            fragment.append(slot);
            return slot;
        });
        pages.append(fragment);
        goToPage(Math.min(focus, source.numPages));
        if ('IntersectionObserver' in window) {
            observer = new IntersectionObserver(entries => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) renderPage(Number(entry.target.dataset.page), expectedVersion);
                });
            }, { root: scroller, rootMargin: '600px 0px', threshold: 0.01 });
            slots.forEach(slot => observer.observe(slot));
        } else {
            slots.forEach(slot => renderPage(Number(slot.dataset.page), expectedVersion));
        }
        await renderPage(currentPage, expectedVersion);
    }

    scroller.addEventListener('scroll', () => {
        if (!pdf || !slots.length) return;
        const top = scroller.getBoundingClientRect().top + 20;
        const visible = slots.find(slot => slot.getBoundingClientRect().bottom > top);
        const number = Number((visible || slots[slots.length - 1]).dataset.page);
        if (number !== currentPage) {
            currentPage = number;
            updateControls();
        }
    }, { passive: true });
    previous.addEventListener('click', () => goToPage(currentPage - 1));
    next.addEventListener('click', () => goToPage(currentPage + 1));
    zoomOut.addEventListener('click', () => {
        zoom = Math.max(0.6, zoom - 0.2);
        buildPages(currentPage).catch(console.error);
    });
    zoomIn.addEventListener('click', () => {
        zoom = Math.min(2.2, zoom + 0.2);
        buildPages(currentPage).catch(console.error);
    });
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => { if (pdf) buildPages(currentPage).catch(console.error); }, 180);
    });

    async function load() {
        try {
            pdf = await pdfjs.getDocument({
                url: viewer.dataset.url,
                cMapUrl: new URL('../vendor/pdfjs/cmaps/', import.meta.url).href,
                cMapPacked: true,
                standardFontDataUrl: new URL('../vendor/pdfjs/standard_fonts/', import.meta.url).href,
            }).promise;
            updateControls();
            await buildPages();
        } catch (error) {
            clearPages();
            pages.innerHTML = '<p class="contract-pdf-error" role="alert">No fue posible mostrar el contrato aquí. Usa «Abrir PDF en otra pestaña».</p>';
            console.error('No se pudo cargar el PDF del contrato:', error);
        }
    }

    updateControls();
    load();
}
