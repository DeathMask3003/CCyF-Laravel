(() => {
    const form = document.querySelector('[data-preval-filters]');
    const panel = document.querySelector('.preval-panel');
    if (!form || !panel || !window.fetch || !window.AbortController || !window.DOMParser) return;

    const search = form.querySelector('[name="buscar"]');
    const status = form.querySelector('[data-preval-filter-status]');
    const clear = form.querySelector('[data-preval-clear]');
    let controller = null;
    let debounce = null;
    let requestNumber = 0;
    let live = true;
    form.classList.add('is-live');

    const filterUrl = () => {
        const url = new URL(form.action, location.href);
        for (const [name, value] of new FormData(form)) {
            if (String(value).trim() !== '') url.searchParams.set(name, String(value));
        }
        return url;
    };

    const replaceSection = (incoming, selector, required = true) => {
        const current = panel.querySelector(selector);
        const replacement = incoming.querySelector(selector);
        if (required && (!current || !replacement)) throw new Error(`Falta la sección ${selector}.`);
        if (current && replacement) current.replaceWith(replacement);
    };

    async function update() {
        if (!live) return;
        clearTimeout(debounce);
        controller?.abort();
        controller = new AbortController();
        const thisRequest = ++requestNumber;
        const url = filterUrl();
        panel.querySelector('[data-preval-results]')?.setAttribute('aria-busy', 'true');
        status.textContent = 'Actualizando resultados…';

        try {
            const response = await fetch(url, {
                credentials: 'same-origin', signal: controller.signal,
                headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!response.ok) throw new Error(`No se pudieron consultar los resultados (${response.status}).`);
            const html = new DOMParser().parseFromString(await response.text(), 'text/html');
            const incoming = html.querySelector('.preval-panel');
            if (!incoming || !incoming.querySelector('[data-preval-results]')) throw new Error('La respuesta no contiene la lista de expedientes.');
            if (thisRequest !== requestNumber) return;

            replaceSection(incoming, '.tracking-tabs');
            replaceSection(incoming, '.preval-intro');
            replaceSection(incoming, '.preval-compare-bar');
            replaceSection(incoming, '.preval-summary-bar', false);
            replaceSection(incoming, '[data-preval-results]');
            const headingCount = document.querySelector('.review-heading .pill');
            const nextHeadingCount = html.querySelector('.review-heading .pill');
            if (headingCount && nextHeadingCount) headingCount.replaceWith(nextHeadingCount);
            history.replaceState(null, '', url);
            status.textContent = '';
        } catch (error) {
            if (error.name === 'AbortError' || thisRequest !== requestNumber) return;
            console.error('No se pudieron actualizar las prevaluaciones:', error);
            live = false;
            form.classList.remove('is-live');
            status.textContent = 'No se pudo actualizar. Usa el botón Buscar.';
        } finally {
            if (thisRequest === requestNumber) {
                panel.querySelector('[data-preval-results]')?.setAttribute('aria-busy', 'false');
                controller = null;
            }
        }
    }

    form.querySelectorAll('select').forEach(select => select.addEventListener('change', update));
    search?.addEventListener('input', () => {
        if (!live) return;
        clearTimeout(debounce);
        controller?.abort();
        requestNumber++;
        panel.querySelector('[data-preval-results]')?.setAttribute('aria-busy', 'false');
        debounce = setTimeout(update, 350);
    });
    form.addEventListener('submit', event => {
        if (!live) return;
        event.preventDefault();
        update();
    });
    clear?.addEventListener('click', event => {
        if (!live) return;
        event.preventDefault();
        const plantel = form.querySelector('[name="plantel"]');
        const estado = form.querySelector('[name="estado"]');
        if (plantel) plantel.value = '';
        if (estado) estado.value = 'pendiente';
        if (search) search.value = '';
        update();
    });
})();
