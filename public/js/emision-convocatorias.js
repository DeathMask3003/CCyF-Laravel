document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('emision-form');
    if (!form) return;

    const optionsElement = document.getElementById('emision-campus-options');
    const tbody = document.getElementById('emision-table-body');
    if (!optionsElement || !tbody) return;
    const campuses = JSON.parse(optionsElement.textContent).sort((left, right) => {
        const group = campus => /^plantel\s/i.test(campus.nombre) ? 0 : 1;
        return group(left) - group(right) || left.nombre.localeCompare(right.nombre, 'es', { numeric: true });
    });
    const search = document.getElementById('emision-campus-search');
    const list = document.getElementById('emision-suggestions');
    const add = document.getElementById('emision-add-campus');
    const empty = document.getElementById('emision-empty-row');
    const count = document.getElementById('emision-count');
    const notice = document.getElementById('emision-data-notice');
    let selected = null;
    let highlighted = -1;
    const norm = text => text.toLocaleLowerCase('es').normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    const added = () => new Set([...tbody.querySelectorAll('tr[data-campus-id]')].map(row => Number(row.dataset.campusId)));
    const refresh = () => {
        const total = added().size;
        count.textContent = total;
        empty.hidden = total > 0;
        add.disabled = !selected || added().has(selected.id);
        const incomplete = [...tbody.querySelectorAll('tr[data-campus-id]')].filter(row =>
            ['espacio', 'matricula', 'monto', 'garantia'].some(field =>
                !row.querySelector(`[name$="[${field}]"]`)?.value.trim()));
        if (notice) {
            notice.hidden = incomplete.length === 0;
            notice.textContent = incomplete.length
                ? `Faltan datos de espacio, matrícula, monto mensual o garantía en ${incomplete.length} ${incomplete.length === 1 ? 'plantel' : 'planteles'}. Completa los campos vacíos de la tabla; no se usarán importes de otro servicio.`
                : '';
        }
    };
    const hide = () => { list.hidden = true; search.setAttribute('aria-expanded', 'false'); highlighted = -1; };
    const choose = campus => {
        selected = campus;
        search.value = campus.nombre;
        add.disabled = false;
        hide();
        add.focus();
    };
    const suggestions = () => {
        selected = null;
        add.disabled = true;
        list.replaceChildren();
        const term = norm(search.value.trim());
        const matches = campuses.filter(campus => !added().has(campus.id) && norm(campus.nombre).includes(term));
        if (!matches.length) {
            const item = document.createElement('div');
            item.className = 'emision-suggestion-empty';
            item.textContent = term ? 'No hay planteles disponibles con esa búsqueda.' : 'Todos los planteles están agregados.';
            list.append(item);
        } else {
            const summary = document.createElement('div');
            summary.className = 'emision-suggestion-empty';
            summary.setAttribute('role', 'note');
            summary.textContent = `${matches.length} disponibles · Desplázate o escribe para filtrar`;
            list.append(summary);
            matches.forEach(campus => {
                const item = document.createElement('button');
                item.type = 'button';
                item.role = 'option';
                item.dataset.id = campus.id;
                item.textContent = campus.nombre;
                item.addEventListener('click', () => choose(campus));
                list.append(item);
            });
        }
        list.hidden = false;
        search.setAttribute('aria-expanded', 'true');
        highlighted = -1;
    };
    search.addEventListener('input', suggestions);
    search.addEventListener('focus', suggestions);
    search.addEventListener('keydown', event => {
        const items = [...list.querySelectorAll('[role=option]')];
        if (event.key === 'Escape') { hide(); return; }
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            if (list.hidden) suggestions();
            const available = [...list.querySelectorAll('[role=option]')];
            if (!available.length) return;
            highlighted = (highlighted + (event.key === 'ArrowDown' ? 1 : -1) + available.length) % available.length;
            available.forEach((item, index) => item.classList.toggle('active', index === highlighted));
            available[highlighted].scrollIntoView({ block: 'nearest' });
        }
        if (event.key === 'Enter') {
            event.preventDefault();
            const choice = items[highlighted >= 0 ? highlighted : 0];
            if (choice) choose(campuses.find(campus => campus.id === Number(choice.dataset.id)));
            else if (selected) add.click();
        }
    });
    document.addEventListener('click', event => {
        if (!event.target.closest('.emision-combobox')) hide();
    });
    add.addEventListener('click', () => {
        if (!selected || added().has(selected.id)) return;
        const template = document.getElementById('emision-campus-row-' + selected.id);
        if (!template) return;
        tbody.insertBefore(template.content.cloneNode(true), empty);
        selected = null;
        search.value = '';
        refresh();
        search.focus();
        hide();
    });
    tbody.addEventListener('click', event => {
        const button = event.target.closest('[data-remove-campus]');
        if (!button) return;
        button.closest('tr').remove();
        refresh();
        search.focus();
        hide();
    });
    tbody.addEventListener('input', refresh);
    document.getElementById('emision-apply-date')?.addEventListener('click', () => {
        const input = document.getElementById('emision-bulk-date');
        if (!input.value) { input.focus(); return; }
        tbody.querySelectorAll('tr[data-campus-id] input[type=date]').forEach(field => { field.value = input.value; });
    });
    form.addEventListener('submit', event => {
        if (added().size) return;
        event.preventDefault();
        search.focus();
        suggestions();
    });
    refresh();
});
