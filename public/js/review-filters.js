(() => {
    document.querySelectorAll('form[data-auto-filter]').forEach(form => {
        const search = form.querySelector('[data-filter-search]');
        const submit = form.querySelector('[data-filter-submit]');
        let timer;
        let composing = false;

        // Keep the submit button available when JavaScript is disabled.
        if (submit) submit.hidden = true;
        form.classList.add('is-auto');

        const apply = () => {
            window.clearTimeout(timer);
            form.requestSubmit();
        };

        form.querySelectorAll('select').forEach(select => {
            select.addEventListener('change', apply);
        });

        if (!search) return;
        search.addEventListener('compositionstart', () => { composing = true; });
        search.addEventListener('compositionend', () => {
            composing = false;
            search.dispatchEvent(new Event('input'));
        });
        search.addEventListener('input', () => {
            window.clearTimeout(timer);
            if (composing) return;
            timer = window.setTimeout(() => {
                const current = (new URLSearchParams(window.location.search).get('buscar') ?? '').trim();
                if (search.value.trim() !== current) apply();
            }, 650);
        });
        search.addEventListener('search', () => {
            if (search.value === '') apply();
        });
    });
})();
