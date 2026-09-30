import * as pdfjs from '../vendor/pdfjs/pdf.min.mjs';

pdfjs.GlobalWorkerOptions.workerSrc = new URL('../vendor/pdfjs/pdf.worker.min.mjs', import.meta.url).href;

const dialog = document.getElementById('preval-dialog');
if (dialog) {
    const links = [...dialog.querySelectorAll('[data-preval-pdf]')];
    const stage = document.getElementById('preval-pdf-stage');
    const scroller = document.getElementById('preval-pdf-canvas-wrap');
    const pagesHost = document.getElementById('preval-pdf-pages');
    const empty = document.getElementById('preval-pdf-empty');
    const error = document.getElementById('preval-pdf-error');
    const open = document.getElementById('preval-pdf-open');
    const name = document.getElementById('preval-pdf-name');
    const count = document.getElementById('preval-page-count');
    const zoomLabel = document.getElementById('preval-zoom-label');
    const reviewJump = document.getElementById('preval-review-jump');
    const checklist = dialog.querySelector('.preval-checklist');
    const criteria = [...dialog.querySelectorAll('[data-preval-criterion]')];
    const previous = document.getElementById('preval-page-prev');
    const next = document.getElementById('preval-page-next');
    let pdf = null;
    let pageNumber = 1;
    let zoom = 1;
    let requestId = 0;
    let renderVersion = 0;
    let observer = null;
    let pageSlots = [];
    let activeLink = null;
    let guidedRequest = -1;
    let highlightTimer;
    const renderTasks = new Set();
    const viewRequests = new Set();

    async function markViewed(link) {
        const url = link?.dataset.viewedUrl;
        if (!url || viewRequests.has(url)) return false;
        if (link.classList.contains('is-viewed')) return true;
        viewRequests.add(url);
        try {
            const token = dialog.querySelector('input[name="_token"]')?.value;
            const response = await fetch(url, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': token || '', 'Accept': 'application/json' },
            });
            if (!response.ok) throw new Error(`No se pudo guardar la visualización (${response.status}).`);
            link.classList.add('is-viewed');
            const mark = link.querySelector('.preval-viewed-mark');
            if (mark) {
                mark.setAttribute('aria-label', 'PDF visto');
                mark.title = 'PDF visto';
            }
            return true;
        } catch (exception) {
            console.error('No se pudo guardar la visualización del PDF:', exception);
            return false;
        } finally {
            viewRequests.delete(url);
        }
    }

    function highlightCriterion(criterion, moveToIt = false) {
        clearTimeout(highlightTimer);
        criteria.forEach(item => item.classList.remove('is-review-target'));
        void criterion.offsetWidth;
        criterion.classList.add('is-review-target');
        highlightTimer = setTimeout(() => criterion.classList.remove('is-review-target'), 4200);

        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (moveToIt) {
            criterion.scrollIntoView({ behavior: reducedMotion ? 'instant' : 'smooth', block: 'center' });
            const choice = criterion.querySelector('input[type="radio"]:checked:not(:disabled)')
                || criterion.querySelector('input[type="radio"]:not(:disabled)');
            if (!choice) criterion.tabIndex = -1;
            (choice || criterion).focus({ preventScroll: true });
        } else if (window.matchMedia('(min-width: 1251px)').matches) {
            const top = checklist.scrollTop + criterion.getBoundingClientRect().top
                - checklist.getBoundingClientRect().top - 22;
            checklist.scrollTo({ top, behavior: reducedMotion ? 'instant' : 'smooth' });
        }
    }

    function guideToCriterion(link) {
        const criterion = criteria.find(item => item.dataset.prevalCriterion === link.dataset.prevalField);
        if (!criterion) return;
        reviewJump.hidden = false;
        reviewJump.textContent = dialog.dataset.canEvaluate === '1' ? 'Ir a calificar ↓' : 'Ver calificación ↓';
        reviewJump.setAttribute('aria-label', `${reviewJump.textContent} ${link.dataset.pdfName}`);
        reviewJump.onclick = () => highlightCriterion(criterion, true);
        highlightCriterion(criterion);
    }

    const updateControls = () => {
        count.textContent = `Página ${pageNumber} de ${pdf?.numPages ?? 1}`;
        zoomLabel.textContent = `${Math.round(zoom * 100)} %`;
        previous.disabled = !pdf || pageNumber <= 1;
        next.disabled = !pdf || pageNumber >= pdf.numPages;
    };

    const clearPages = () => {
        renderVersion++;
        observer?.disconnect();
        observer = null;
        renderTasks.forEach(task => task.cancel());
        renderTasks.clear();
        pageSlots = [];
        pagesHost.replaceChildren();
    };

    const scrollToPage = number => {
        const slot = pageSlots[number - 1];
        if (!slot) return;
        pageNumber = number;
        updateControls();
        const top = slot.getBoundingClientRect().top - scroller.getBoundingClientRect().top + scroller.scrollTop;
        scroller.scrollTop = top;
    };

    const updatePageFromScroll = () => {
        if (!pdf || !pageSlots.length) return;
        const top = scroller.getBoundingClientRect().top + 20;
        const visible = pageSlots.find(slot => slot.getBoundingClientRect().bottom > top);
        const number = Number((visible || pageSlots[pageSlots.length - 1]).dataset.page);
        if (pageNumber !== number) {
            pageNumber = number;
            updateControls();
        }
    };

    async function renderPage(number, version) {
        const slot = pageSlots[number - 1];
        if (!slot || slot.dataset.state !== 'pending' || !pdf) return;
        slot.dataset.state = 'rendering';
        const source = pdf;
        let task = null;
        try {
            const page = await source.getPage(number);
            if (version !== renderVersion || source !== pdf) return;
            const base = page.getViewport({ scale: 1 });
            const availableWidth = Math.max(260, scroller.clientWidth - 34);
            const viewport = page.getViewport({ scale: availableWidth / base.width * zoom });
            const pixelRatio = Math.min(window.devicePixelRatio || 1, 1.5);
            const canvas = document.createElement('canvas');
            canvas.width = Math.max(1, Math.floor(viewport.width * pixelRatio));
            canvas.height = Math.max(1, Math.floor(viewport.height * pixelRatio));
            canvas.style.width = `${Math.floor(viewport.width)}px`;
            canvas.style.height = `${Math.floor(viewport.height)}px`;
            canvas.setAttribute('role', 'img');
            canvas.setAttribute('aria-label', `Página ${number} de ${source.numPages}`);
            task = page.render({ canvasContext: canvas.getContext('2d', { alpha: false }), viewport,
                transform: pixelRatio === 1 ? null : [pixelRatio, 0, 0, pixelRatio, 0, 0] });
            renderTasks.add(task);
            await task.promise;
            if (version !== renderVersion || source !== pdf) return;
            slot.style.width = `${Math.floor(viewport.width)}px`;
            slot.style.minHeight = `${Math.floor(viewport.height)}px`;
            slot.replaceChildren(canvas);
            slot.dataset.state = 'done';
            if (number === 1 && activeLink?.dataset.viewedUrl) {
                const viewedLink = activeLink;
                const selection = requestId;
                markViewed(viewedLink).then(seen => {
                    if (seen && selection === requestId && activeLink === viewedLink && guidedRequest !== selection) {
                        guidedRequest = selection;
                        guideToCriterion(viewedLink);
                    }
                });
            }
            page.cleanup();
        } catch (exception) {
            if (version !== renderVersion || exception?.name === 'RenderingCancelledException') return;
            slot.dataset.state = 'error';
            slot.textContent = `No se pudo mostrar la página ${number}. Usa «Abrir PDF» para verla.`;
            console.error('No se pudo mostrar una página del PDF:', exception);
        } finally {
            if (task) renderTasks.delete(task);
        }
    }

    async function buildPages(focusPage = 1) {
        clearPages();
        if (!pdf) return;
        const version = renderVersion;
        const source = pdf;
        const first = await source.getPage(1);
        if (version !== renderVersion || source !== pdf) return;
        const base = first.getViewport({ scale: 1 });
        const width = Math.max(260, scroller.clientWidth - 34) * zoom;
        const height = width * base.height / base.width;
        const fragment = document.createDocumentFragment();
        pageSlots = Array.from({ length: source.numPages }, (_, index) => {
            const slot = document.createElement('div');
            slot.className = 'preval-pdf-page';
            slot.dataset.page = String(index + 1);
            slot.dataset.state = 'pending';
            slot.style.width = `${Math.floor(width)}px`;
            slot.style.minHeight = `${Math.floor(height)}px`;
            slot.setAttribute('role', 'group');
            slot.setAttribute('aria-label', `Página ${index + 1} de ${source.numPages}`);
            const loading = document.createElement('span');
            loading.className = 'preval-pdf-loading';
            loading.textContent = `Preparando página ${index + 1}…`;
            slot.append(loading);
            fragment.append(slot);
            return slot;
        });
        pagesHost.append(fragment);
        scrollToPage(Math.min(focusPage, source.numPages));
        if ('IntersectionObserver' in window) {
            observer = new IntersectionObserver(entries => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) renderPage(Number(entry.target.dataset.page), version);
                });
            }, { root: scroller, rootMargin: '600px 0px', threshold: 0.01 });
            pageSlots.forEach(slot => observer.observe(slot));
        } else {
            pageSlots.forEach(slot => renderPage(Number(slot.dataset.page), version));
        }
        await renderPage(pageNumber, version);
    }

    async function load(link) {
        const current = ++requestId;
        activeLink = link;
        reviewJump.hidden = true;
        reviewJump.onclick = null;
        clearTimeout(highlightTimer);
        criteria.forEach(item => item.classList.remove('is-review-target'));
        links.forEach(item => item.classList.toggle('selected', item === link));
        name.textContent = link.dataset.pdfName || 'Documento PDF';
        open.href = link.href;
        open.hidden = false;
        empty.hidden = true;
        error.hidden = true;
        stage.hidden = true;
        clearPages();
        if (pdf) {
            const old = pdf;
            pdf = null;
            await old.destroy();
        }
        if (current !== requestId) return;
        pageNumber = 1;
        zoom = 1;
        updateControls();
        try {
            const task = pdfjs.getDocument({ url: link.href, cMapUrl: new URL('../vendor/pdfjs/cmaps/', import.meta.url).href,
                cMapPacked: true, standardFontDataUrl: new URL('../vendor/pdfjs/standard_fonts/', import.meta.url).href });
            const documentPdf = await task.promise;
            if (current !== requestId) { await documentPdf.destroy(); return; }
            pdf = documentPdf;
            stage.hidden = false;
            await buildPages();
        } catch (exception) {
            if (current !== requestId) return;
            stage.hidden = true;
            error.hidden = false;
            console.error('No se pudo mostrar el PDF en el modal:', exception);
        }
    }

    links.forEach(link => link.addEventListener('click', event => { event.preventDefault(); load(link); }));
    previous.addEventListener('click', () => { if (pdf && pageNumber > 1) scrollToPage(pageNumber - 1); });
    next.addEventListener('click', () => { if (pdf && pageNumber < pdf.numPages) scrollToPage(pageNumber + 1); });
    document.getElementById('preval-zoom-out').addEventListener('click', () => {
        if (!pdf) return;
        zoom = Math.max(0.6, zoom - 0.2);
        buildPages(pageNumber).catch(console.error);
    });
    document.getElementById('preval-zoom-in').addEventListener('click', () => {
        if (!pdf) return;
        zoom = Math.min(2.2, zoom + 0.2);
        buildPages(pageNumber).catch(console.error);
    });
    scroller.addEventListener('scroll', updatePageFromScroll, { passive: true });
    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => { if (pdf) buildPages(pageNumber).catch(console.error); }, 180);
    });
    dialog.addEventListener('close', () => {
        requestId++;
        activeLink = null;
        reviewJump.hidden = true;
        clearTimeout(highlightTimer);
        clearPages();
        const old = pdf;
        pdf = null;
        if (old) old.destroy().catch(console.error);
    });
    if (links.length) load(links[0]);
}
