document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('emision-form');
    if (!form) return;

    const source = document.getElementById('emision-details');
    const editor = document.getElementById('emision-editor');
    if (source && editor) {
        const cleanDraft = html => {
            const documentFragment = new DOMParser().parseFromString(html, 'text/html');
            const allowed = new Set(['P', 'BR', 'STRONG', 'B', 'EM', 'I', 'U', 'H2', 'H3', 'OL', 'UL',
                'LI', 'BLOCKQUOTE', 'TABLE', 'THEAD', 'TBODY', 'TR', 'TH', 'TD', 'A']);
            documentFragment.body.querySelectorAll('script,style,iframe,object,embed,form,input,img,svg').forEach(node => node.remove());
            [...documentFragment.body.querySelectorAll('*')].reverse().forEach(node => {
                const href = node.tagName === 'A' ? node.getAttribute('href') : null;
                [...node.attributes].forEach(attribute => node.removeAttribute(attribute.name));
                if (href && /^https?:\/\//i.test(href)) node.setAttribute('href', href);
                if (!allowed.has(node.tagName)) node.replaceWith(...node.childNodes);
            });
            return documentFragment.body.innerHTML;
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
                return;
            }
            editor.removeAttribute('aria-invalid');
        });
    }

    const checks = [...document.querySelectorAll('.emision-campus-check')];
    const all = document.getElementById('emision-all');
    const count = document.getElementById('emision-count');
    const refresh = () => {
        const selected = checks.filter(box => box.checked).length;
        if (count) count.textContent = selected;
        if (all) {
            all.checked = checks.length > 0 && selected === checks.length;
            all.indeterminate = selected > 0 && selected < checks.length;
        }
        checks.forEach(box => {
            const card = box.closest('[data-campus]');
            card?.classList.toggle('is-selected', box.checked);
            const state = card?.querySelector('.emision-campus-state');
            if (state) state.textContent = box.checked ? 'Incluido' : 'No incluido';
        });
    };
    checks.forEach(box => box.addEventListener('change', refresh));
    all?.addEventListener('change', () => { checks.forEach(box => { box.checked = all.checked; }); refresh(); });
    const search = document.getElementById('emision-campus-search');
    search?.addEventListener('input', () => {
        const term = search.value.trim().toLocaleLowerCase('es');
        document.querySelectorAll('[data-campus]').forEach(card => {
            card.hidden = !card.querySelector('.emision-campus-title strong').textContent.toLocaleLowerCase('es').includes(term);
        });
    });
    document.getElementById('emision-apply-date')?.addEventListener('click', () => {
        const date = document.getElementById('emision-bulk-date').value;
        if (!date) { document.getElementById('emision-bulk-date').focus(); return; }
        checks.filter(box => box.checked).forEach(box => {
            box.closest('[data-campus]').querySelector('input[type=date]').value = date;
        });
    });
    refresh();
});
