document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('emision-form');
    if (!form) return;

    const source = document.getElementById('emision-details');
    const editor = document.getElementById('emision-editor');
    if (source && editor) {
        const cleanDraft = html => {
            const draft = new DOMParser().parseFromString(html, 'text/html');
            const allowed = new Set(['P', 'BR', 'STRONG', 'B', 'EM', 'I', 'U', 'H2', 'H3', 'OL', 'UL',
                'LI', 'BLOCKQUOTE', 'TABLE', 'THEAD', 'TBODY', 'TR', 'TH', 'TD', 'A']);
            draft.body.querySelectorAll('script,style,iframe,object,embed,form,input,img,svg').forEach(node => node.remove());
            [...draft.body.querySelectorAll('*')].reverse().forEach(node => {
                const href = node.tagName === 'A' ? node.getAttribute('href') : null;
                [...node.attributes].forEach(attribute => node.removeAttribute(attribute.name));
                if (href && /^https?:\/\//i.test(href)) node.setAttribute('href', href);
                if (!allowed.has(node.tagName)) node.replaceWith(...node.childNodes);
            });
            return draft.body.innerHTML;
        };
        editor.innerHTML = cleanDraft(source.value);
        form.classList.add('editor-ready');
        editor.addEventListener('paste', event => {
            event.preventDefault();
            document.execCommand('insertText', false, event.clipboardData.getData('text/plain'));
        });
        document.querySelectorAll('.emision-editor-toolbar [data-command]').forEach(button => {
            button.addEventListener('click', () => {
                editor.focus();
                document.execCommand(button.dataset.command, false, button.dataset.value || null);
                source.value = editor.innerHTML;
            });
        });
        editor.addEventListener('input', () => { source.value = editor.innerHTML; });
        form.addEventListener('submit', event => {
            source.value = editor.innerHTML;
            if (!editor.textContent.trim()) {
                event.preventDefault();
                editor.focus();
                editor.setAttribute('aria-invalid', 'true');
            } else {
                editor.removeAttribute('aria-invalid');
            }
        });
    }

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
    let selected = null;
    let highlighted = -1;
    const norm = text => text.toLocaleLowerCase('es').normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    const added = () => new Set([...tbody.querySelectorAll('tr[data-campus-id]')].map(row => Number(row.dataset.campusId)));
    const refresh = () => {
        const total = added().size;
        count.textContent = total;
        empty.hidden = total > 0;
        add.disabled = !selected || added().has(selected.id);
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
