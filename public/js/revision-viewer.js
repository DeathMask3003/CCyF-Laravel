import * as pdfjs from '../vendor/pdfjs/pdf.min.mjs';

pdfjs.GlobalWorkerOptions.workerSrc = new URL('../vendor/pdfjs/pdf.worker.min.mjs', import.meta.url).href;

const dialog = document.getElementById('review-document-dialog');

if (dialog) {
    const title = document.getElementById('review-document-title');
    const message = document.getElementById('review-document-message');
    const scroller = document.getElementById('review-document-scroll');
    const pages = document.getElementById('review-document-pages');
    const imageWrap = document.getElementById('review-document-image-wrap');
    const image = document.getElementById('review-document-image');
    const toolbar = document.getElementById('review-document-toolbar');
    const counter = document.getElementById('review-document-page-count');
    const zoomText = document.getElementById('review-document-zoom');
    const previous = document.getElementById('review-document-prev');
    const next = document.getElementById('review-document-next');
    let pdf = null;
    let slots = [];
    let pageNumber = 1;
    let zoom = 1;
    let loadVersion = 0;
    let renderVersion = 0;
    let observer = null;
    const renderTasks = new Set();

    const updateToolbar = () => {
        counter.textContent = `Página ${pageNumber} de ${pdf?.numPages ?? 1}`;
        zoomText.textContent = `${Math.round(zoom * 100)} %`;
        previous.disabled = !pdf || pageNumber <= 1;
        next.disabled = !pdf || pageNumber >= pdf.numPages;
    };

    const resetPages = () => {
        renderVersion++;
        observer?.disconnect();
        observer = null;
        renderTasks.forEach(task => task.cancel());
        renderTasks.clear();
        slots = [];
        pages.replaceChildren();
    };

    const goToPage = number => {
        const slot = slots[number - 1];
        if (!slot) return;
        pageNumber = number;
        updateToolbar();
        scroller.scrollTop = slot.getBoundingClientRect().top - scroller.getBoundingClientRect().top + scroller.scrollTop;
    };

    async function renderPage(number, version) {
        const slot = slots[number - 1];
        if (!slot || slot.dataset.state !== 'pending' || !pdf) return;
        slot.dataset.state = 'rendering';
        const source = pdf;
        let task;
        try {
            const page = await source.getPage(number);
            if (version !== renderVersion || source !== pdf) return;
            const base = page.getViewport({ scale: 1 });
            const viewport = page.getViewport({ scale: Math.max(250, scroller.clientWidth - 34) / base.width * zoom });
            const ratio = Math.min(window.devicePixelRatio || 1, 1.5);
            const canvas = document.createElement('canvas');
            canvas.width = Math.max(1, Math.floor(viewport.width * ratio));
            canvas.height = Math.max(1, Math.floor(viewport.height * ratio));
            canvas.style.width = `${Math.floor(viewport.width)}px`;
            canvas.style.height = `${Math.floor(viewport.height)}px`;
            canvas.setAttribute('role', 'img');
            canvas.setAttribute('aria-label', `Página ${number} de ${source.numPages}`);
            task = page.render({ canvasContext: canvas.getContext('2d', { alpha: false }), viewport,
                transform: ratio === 1 ? null : [ratio, 0, 0, ratio, 0, 0] });
            renderTasks.add(task);
            await task.promise;
            if (version !== renderVersion || source !== pdf) return;
            slot.style.width = `${Math.floor(viewport.width)}px`;
            slot.style.minHeight = `${Math.floor(viewport.height)}px`;
            slot.replaceChildren(canvas);
            slot.dataset.state = 'done';
            page.cleanup();
        } catch (error) {
            if (version !== renderVersion || error?.name === 'RenderingCancelledException') return;
            slot.dataset.state = 'error';
            slot.textContent = `No se pudo mostrar la página ${number}.`;
            console.error('Error al mostrar documento:', error);
        } finally {
            if (task) renderTasks.delete(task);
        }
    }

    async function buildPages(focusPage = 1) {
        resetPages();
        if (!pdf) return;
        const version = renderVersion;
        const source = pdf;
        const first = await source.getPage(1);
        if (version !== renderVersion || source !== pdf) return;
        const base = first.getViewport({ scale: 1 });
        const width = Math.max(250, scroller.clientWidth - 34) * zoom;
        const height = width * base.height / base.width;
        const fragment = document.createDocumentFragment();
        slots = Array.from({ length: source.numPages }, (_, index) => {
            const slot = document.createElement('div');
            slot.className = 'review-document-page';
            slot.dataset.page = String(index + 1);
            slot.dataset.state = 'pending';
            slot.style.width = `${Math.floor(width)}px`;
            slot.style.minHeight = `${Math.floor(height)}px`;
            slot.setAttribute('role', 'group');
            slot.setAttribute('aria-label', `Página ${index + 1} de ${source.numPages}`);
            const loading = document.createElement('span');
            loading.textContent = `Preparando página ${index + 1}…`;
            slot.append(loading);
            fragment.append(slot);
            return slot;
        });
        pages.append(fragment);
        goToPage(Math.min(focusPage, source.numPages));
        if ('IntersectionObserver' in window) {
            observer = new IntersectionObserver(entries => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) renderPage(Number(entry.target.dataset.page), version);
                });
            }, { root: scroller, rootMargin: '600px 0px', threshold: 0.01 });
            slots.forEach(slot => observer.observe(slot));
        } else {
            slots.forEach(slot => renderPage(Number(slot.dataset.page), version));
        }
        await renderPage(pageNumber, version);
    }

    async function showDocument(link) {
        const version = ++loadVersion;
        resetPages();
        const old = pdf;
        pdf = null;
        if (old) await old.destroy();
        if (version !== loadVersion) return;
        title.textContent = link.dataset.documentName || 'Documento';
        message.textContent = 'Preparando documento…';
        message.hidden = false;
        scroller.hidden = true;
        imageWrap.hidden = true;
        toolbar.hidden = true;
        image.removeAttribute('src');
        zoom = 1;
        pageNumber = 1;
        if (!dialog.open) dialog.showModal();

        const mime = link.dataset.documentMime;
        if (mime?.startsWith('image/')) {
            image.alt = title.textContent;
            image.onload = () => { if (version === loadVersion) { message.hidden = true; imageWrap.hidden = false; } };
            image.onerror = () => { if (version === loadVersion) message.textContent = 'No fue posible mostrar esta imagen.'; };
            image.src = link.href;
            return;
        }
        if (mime !== 'application/pdf') {
            message.textContent = 'Este formato no se puede visualizar en el modal.';
            return;
        }
        try {
            const task = pdfjs.getDocument({ url: link.href,
                cMapUrl: new URL('../vendor/pdfjs/cmaps/', import.meta.url).href,
                cMapPacked: true,
                standardFontDataUrl: new URL('../vendor/pdfjs/standard_fonts/', import.meta.url).href });
            const loaded = await task.promise;
            if (version !== loadVersion) { await loaded.destroy(); return; }
            pdf = loaded;
            message.hidden = true;
            scroller.hidden = false;
            toolbar.hidden = false;
            updateToolbar();
            await buildPages();
        } catch (error) {
            if (version !== loadVersion) return;
            scroller.hidden = true;
            toolbar.hidden = true;
            message.textContent = 'No fue posible mostrar este PDF.';
            message.hidden = false;
            console.error('Error al abrir documento:', error);
        }
    }

    document.querySelectorAll('[data-review-document]').forEach(link => {
        link.addEventListener('click', event => { event.preventDefault(); showDocument(link); });
    });
    document.getElementById('review-document-close').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
    dialog.addEventListener('close', () => {
        loadVersion++;
        resetPages();
        image.removeAttribute('src');
        const old = pdf;
        pdf = null;
        if (old) old.destroy().catch(console.error);
    });
    previous.addEventListener('click', () => { if (pdf && pageNumber > 1) goToPage(pageNumber - 1); });
    next.addEventListener('click', () => { if (pdf && pageNumber < pdf.numPages) goToPage(pageNumber + 1); });
    document.getElementById('review-document-zoom-out').addEventListener('click', () => {
        if (!pdf) return;
        zoom = Math.max(0.6, zoom - 0.2);
        buildPages(pageNumber).catch(console.error);
    });
    document.getElementById('review-document-zoom-in').addEventListener('click', () => {
        if (!pdf) return;
        zoom = Math.min(2.2, zoom + 0.2);
        buildPages(pageNumber).catch(console.error);
    });
    scroller.addEventListener('scroll', () => {
        if (!pdf || !slots.length) return;
        const top = scroller.getBoundingClientRect().top + 20;
        const visible = slots.find(slot => slot.getBoundingClientRect().bottom > top);
        const number = Number((visible || slots[slots.length - 1]).dataset.page);
        if (pageNumber !== number) { pageNumber = number; updateToolbar(); }
    }, { passive: true });
    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => { if (pdf) buildPages(pageNumber).catch(console.error); }, 180);
    });
}
