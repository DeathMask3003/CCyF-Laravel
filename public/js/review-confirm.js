(() => {
    const form = document.querySelector('form[data-review-confirm]');
    const dialog = document.querySelector('[data-review-dialog]');
    if (!form || !dialog) return;

    const title = dialog.querySelector('[data-review-title]');
    const description = dialog.querySelector('[data-review-description]');
    const contextLabel = dialog.querySelector('[data-review-context-label]');
    const context = dialog.querySelector('[data-review-context]');
    const meta = dialog.querySelector('[data-review-meta]');
    const response = dialog.querySelector('[data-review-response]');
    const note = dialog.querySelector('[data-review-note]');
    const accept = dialog.querySelector('[data-review-accept]');
    const cancel = dialog.querySelector('[data-review-cancel]');
    const bulkError = form.querySelector('[data-review-bulk-error]');
    const selectAll = form.querySelector('[data-review-select-all]');
    const selectedCount = form.querySelector('[data-review-count]');
    const recordCheckboxes = Array.from(document.querySelectorAll('input[name="registros[]"][form="' + form.id + '"]'));
    let confirmed = false;

    const selectedRecords = () => recordCheckboxes.filter(input => input.checked);
    const updateSelection = () => {
        if (!selectAll) return;
        const count = selectedRecords().length;
        selectAll.checked = count === recordCheckboxes.length && count > 0;
        selectAll.indeterminate = count > 0 && count < recordCheckboxes.length;
        selectedCount.textContent = count === 1 ? '1 seleccionado' : `${count} seleccionados`;
        if (count > 0 && bulkError) bulkError.hidden = true;
    };

    form.addEventListener('submit', event => {
        if (confirmed) return;
        event.preventDefault();

        const answer = form.elements.namedItem('respuesta')?.value.trim() || '';
        if (form.dataset.reviewConfirm === 'bulk') {
            const selected = selectedRecords();
            if (!selected.length) {
                if (bulkError) bulkError.hidden = false;
                return;
            }
            if (bulkError) bulkError.hidden = true;
            const folios = selected.slice(0, 3).map(input =>
                input.closest('.review-card')?.querySelector('.review-card-id strong')?.textContent.trim() || ''
            ).filter(Boolean);
            const decision = form.elements.namedItem('decision')?.value;
            const notAccepted = decision === 'no_aceptado';
            title.textContent = notAccepted ? 'No aceptar propuestas' : 'No designar propuestas';
            description.textContent = notAccepted
                ? 'Las propuestas seleccionadas quedarán como no aceptadas.'
                : 'Las propuestas seleccionadas quedarán como no designadas.';
            contextLabel.textContent = selected.length === 1 ? 'Propuesta seleccionada' : 'Propuestas seleccionadas';
            context.textContent = selected.length === 1 ? '1 expediente' : `${selected.length} expedientes`;
            meta.textContent = folios.join(' · ') + (selected.length > 3 ? ` · y ${selected.length - 3} más` : '');
            note.textContent = 'Se preparará una carta individual para cada participante. Los correos se enviarán cuando el servicio esté configurado.';
            accept.textContent = 'Confirmar y notificar';
        } else {
            const decision = form.elements.namedItem('decision');
            const chosen = decision?.options[decision.selectedIndex]?.textContent.trim() || 'Resultado';
            const designated = decision?.value === 'designado';
            title.textContent = chosen;
            description.textContent = designated
                ? 'Se registrará la designación para este plantel.'
                : 'Se registrará esta decisión en el expediente.';
            contextLabel.textContent = 'Expediente';
            context.textContent = form.dataset.reviewFolio || 'Registro actual';
            const place = [form.dataset.reviewService, form.dataset.reviewCampus].filter(Boolean).join(' · ');
            const dates = designated
                ? [form.elements.namedItem('fecha_inicio')?.value, form.elements.namedItem('fecha_fin')?.value]
                    .filter(Boolean).join(' → ')
                : '';
            meta.textContent = [place, dates].filter(Boolean).join(' · ');
        }
        response.textContent = answer;
        dialog.showModal();
    });

    form.addEventListener('change', event => {
        if (event.target.name === 'registros[]' && bulkError) bulkError.hidden = true;
    });
    recordCheckboxes.forEach(input => {
        input.addEventListener('change', updateSelection);
    });
    selectAll?.addEventListener('change', () => {
        recordCheckboxes.forEach(input => { input.checked = selectAll.checked; });
        updateSelection();
    });
    updateSelection();
    cancel.addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', event => {
        if (event.target === dialog) dialog.close();
    });
    accept.addEventListener('click', () => {
        confirmed = true;
        dialog.close();
        form.requestSubmit();
    });
})();
