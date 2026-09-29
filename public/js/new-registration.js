document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-new-registration]');
    if (!root) return;

    const form = root.querySelector('[data-registration-form]');
    const type = form.querySelector('[name="tipo_documento_id"]');
    const campus = form.querySelector('[name="plantel_id"]');
    const comments = form.querySelector('[name="comentarios"]');
    const prices = [...form.querySelectorAll('[name^="precios["]')];
    const files = [...form.querySelectorAll('.newreg-upload-card input[type="file"]')];
    const search = root.querySelector('[data-product-search]');
    const clearSearchButton = root.querySelector('[data-clear-search]');
    const productRows = [...root.querySelectorAll('.newreg-price-row')];
    const normalize = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es');
    const money = new Intl.NumberFormat('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const priceIdleTimers = new WeakMap();
    const priceHideTimers = new WeakMap();
    let activePriceTooltip = null;

    function validPrice(input) {
        return input.value !== '' && input.checkValidity() && Number.isFinite(Number(input.value));
    }

    function hidePriceTooltip(input) {
        const row = input.closest('.newreg-price-row');
        const tooltip = row.querySelector('.newreg-price-tooltip');
        clearTimeout(priceHideTimers.get(input));
        tooltip.hidden = true;
        row.classList.remove('price-tip-visible');
        if (activePriceTooltip === tooltip) activePriceTooltip = null;
    }

    function showPriceTooltip(input) {
        if (!validPrice(input)) return;
        const tooltip = input.closest('.newreg-price-row').querySelector('.newreg-price-tooltip');
        if (activePriceTooltip && activePriceTooltip !== tooltip) {
            activePriceTooltip.hidden = true;
            activePriceTooltip.closest('.newreg-price-row').classList.remove('price-tip-visible');
        }
        clearTimeout(priceHideTimers.get(input));
        tooltip.querySelector('[data-price-confirmation]').textContent = `$ ${money.format(Number(input.value))}`;
        tooltip.hidden = false;
        input.closest('.newreg-price-row').classList.add('price-tip-visible');
        activePriceTooltip = tooltip;
        priceHideTimers.set(input, setTimeout(() => hidePriceTooltip(input), 3000));
    }

    function filterProducts() {
        if (!search) return;
        const query = normalize(search.value.trim());
        let visible = 0;
        productRows.forEach((row) => {
            row.hidden = !normalize(row.dataset.productName || '').includes(query);
            if (!row.hidden) visible++;
        });
        root.querySelector('[data-product-search-count]').textContent = `${visible} de ${productRows.length}`;
        root.querySelector('[data-product-empty]').hidden = visible !== 0;
        clearSearchButton.hidden = !query;
    }

    async function isPdfContent(file) {
        const [head, tail] = await Promise.all([
            file.slice(0, 12).arrayBuffer(),
            file.slice(Math.max(0, file.size - 4096)).arrayBuffer(),
        ]);
        const decoder = new TextDecoder();
        return /^(?:\uFEFF)?%PDF-(?:1\.[0-7]|2\.0)/.test(decoder.decode(head))
            && decoder.decode(tail).includes('%%EOF');
    }

    async function updateFile(input) {
        const card = input.closest('.newreg-upload-card');
        const label = card.querySelector('[data-file-label]');
        const status = card.querySelector('.newreg-upload-status');
        const file = input.files[0];
        let error = '';
        if (file && !/\.pdf$/i.test(file.name)) {
            error = 'El archivo debe tener extensión .pdf.';
        } else if (file && file.size > 3 * 1024 * 1024) {
            error = 'El PDF no debe superar 3 MB.';
        } else if (file) {
            input.setCustomValidity('Verificando el archivo PDF.');
            label.textContent = 'Verificando PDF…';
            status.textContent = '…';
            try {
                if (!await isPdfContent(file)) {
                    error = 'Este archivo no es un PDF válido. Expórtalo nuevamente a PDF.';
                }
            } catch {
                error = 'No se pudo leer este PDF. Vuelve a adjuntarlo.';
            }
        }
        if (input.files[0] !== file) return;
        input.setCustomValidity(error);
        card.classList.toggle('has-file', Boolean(file) && !error);
        card.classList.toggle('is-invalid', Boolean(error));
        label.textContent = error || (file ? file.name : 'Seleccionar PDF · máximo 3 MB');
        status.textContent = error ? '!' : (file ? '✓' : '＋');
        updateProgress();
    }

    function updateProgress() {
        const filledPrices = prices.filter((input) => input.value !== '' && input.checkValidity()).length;
        const filledFiles = files.filter((input) => input.files.length > 0 && input.checkValidity()).length;
        const validComments = comments.value.trim().length > 0;
        const total = 3 + prices.length + files.length;
        const completed = Number(Boolean(type.value)) + Number(Boolean(campus.value)) + Number(validComments) + filledPrices + filledFiles;
        const percentage = total ? Math.round(completed / total * 100) : 0;

        root.querySelector('[data-prices-summary]').textContent = `${filledPrices} de ${prices.length}`;
        root.querySelector('[data-docs-summary]').textContent = `${filledFiles} de ${files.length}`;
        root.querySelector('[data-campus-summary]').textContent = campus.value ? campus.selectedOptions[0].textContent.trim() : 'Por seleccionar';
        root.querySelector('[data-progress-fill]').style.width = `${percentage}%`;
        root.querySelector('[data-progress-track]').setAttribute('aria-valuenow', String(percentage));
        root.querySelector('[data-progress-label]').textContent = percentage === 100 ? 'Propuesta lista para revisar y enviar' : `${percentage}% de la información completada`;
        root.querySelector('[data-comments-count]').textContent = `${comments.value.length.toLocaleString('es-MX')} / 5,000`;
        prices.forEach((input) => input.closest('.newreg-price-row').classList.toggle('is-filled', input.value !== '' && input.checkValidity()));
    }

    search?.addEventListener('input', filterProducts);
    clearSearchButton?.addEventListener('click', () => {
        search.value = '';
        filterProducts();
        search.focus();
    });
    form.querySelector('.newreg-actions .button')?.addEventListener('click', () => {
        if (search?.value) {
            search.value = '';
            filterProducts();
        }
    });
    form.addEventListener('input', updateProgress);
    form.addEventListener('change', updateProgress);
    prices.forEach((input) => {
        if (validPrice(input)) input.value = Number(input.value).toFixed(2);
        input.addEventListener('input', () => {
            clearTimeout(priceIdleTimers.get(input));
            hidePriceTooltip(input);
            if (validPrice(input)) {
                priceIdleTimers.set(input, setTimeout(() => showPriceTooltip(input), 900));
            }
        });
        input.addEventListener('blur', () => {
            clearTimeout(priceIdleTimers.get(input));
            if (validPrice(input)) {
                input.value = Number(input.value).toFixed(2);
                showPriceTooltip(input);
            } else {
                hidePriceTooltip(input);
            }
            updateProgress();
        });
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                input.blur();
            }
        });
    });
    files.forEach((input) => input.addEventListener('change', () => updateFile(input)));
    form.addEventListener('invalid', (event) => {
        if (event.target.closest('.newreg-price-row') && search?.value) {
            search.value = '';
            filterProducts();
        }
    }, true);

    const submissionDialog = root.querySelector('[data-submission-dialog]');
    if (submissionDialog) {
        const title = submissionDialog.querySelector('[data-submission-title]');
        const message = submissionDialog.querySelector('[data-submission-message]');
        const note = submissionDialog.querySelector('[data-submission-note]');
        let submitting = false;
        const resetSubmission = () => {
            submitting = false;
            if (submissionDialog.open) submissionDialog.close();
            document.documentElement.classList.remove('newreg-is-submitting');
        };
        submissionDialog.addEventListener('cancel', (event) => event.preventDefault());
        window.addEventListener('pageshow', resetSubmission);
        form.addEventListener('submit', (event) => {
            if (submitting) {
                event.preventDefault();
                return;
            }
            submitting = true;
            const draft = event.submitter?.hasAttribute('formnovalidate') ?? false;
            if (draft) {
                title.textContent = 'Guardando borrador';
                message.textContent = 'Estamos guardando los precios de tu propuesta para que puedas continuar después.';
                note.textContent = 'Espera un momento mientras se guardan los cambios.';
            } else {
                const count = files.filter((input) => input.files.length > 0).length;
                title.textContent = 'Subiendo documentos';
                message.textContent = `Estamos subiendo ${count} ${count === 1 ? 'documento PDF' : 'documentos PDF'} y creando tu expediente.`;
                note.textContent = 'Conserva esta ventana abierta hasta recibir tu folio.';
            }
            submissionDialog.showModal();
            document.documentElement.classList.add('newreg-is-submitting');
        });
    }

    updateProgress();
    filterProducts();
});
