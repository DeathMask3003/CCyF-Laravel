const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const script = fs.readFileSync(path.join(__dirname, '../../public/js/contract-tinymce.js'), 'utf8');

function page({valid = true, uploads = []} = {}) {
    let onReady;
    let onSubmit;
    let submits = 0;
    const status = {hidden: true, dataset: {}, textContent: ''};
    const source = {dataset: {tinyBase: '/vendor/tinymce', uploadUrl: '/imagenes'}, value: '<p>Texto inicial</p>', focus() {}};
    const button = {disabled: false, textContent: 'Guardar versión revisada'};
    const field = {
        willValidate: true, name: 'institution_signer', labels: [{textContent: 'Representante del COBAEM'}],
        checkValidity: () => valid, focus() {}, scrollIntoView() {},
    };
    class HTMLFormElement {
        submit() { submits++; }
    }
    const form = new HTMLFormElement();
    form.elements = [field];
    form.querySelector = selector => selector === 'button[type="submit"]' ? button : {value: 'csrf'};
    form.addEventListener = (name, listener) => { if (name === 'submit') onSubmit = listener; };
    const editor = {
        on() {}, getBody: () => ({style: {}}),
        uploadImages: async () => uploads,
        getContent: options => options?.format === 'text' ? 'Texto inicial' : '<p>Texto inicial</p>',
        save() { source.value = '<p>Texto inicial</p>'; },
        focus() {},
    };
    const tinymce = {init: async options => { options.setup(editor); }, get: () => editor};
    const document = {
        addEventListener: (name, listener) => { if (name === 'DOMContentLoaded') onReady = listener; },
        getElementById: id => ({'contract-body': source, 'contract-template-form': form,
            'contract-save-status': status}[id] || null),
        querySelector: () => null,
        querySelectorAll: () => [],
    };
    vm.runInNewContext(script, {
        document, window: {tinymce, addEventListener() {}}, HTMLFormElement, Number, console,
    });
    onReady();
    return {
        status, button, field, get submits() { return submits; },
        submit: () => onSubmit({preventDefault() {}}),
    };
}

test('muestra el campo obligatorio que impide guardar', async () => {
    const view = page({valid: false});
    await view.submit();
    assert.equal(view.submits, 0);
    assert.match(view.status.textContent, /Representante del COBAEM/);
    assert.equal(view.status.dataset.type, 'error');
    assert.equal(view.button.disabled, false);
});

test('guarda una sola vez con los campos completos', async () => {
    const view = page();
    await view.submit();
    assert.equal(view.submits, 1);
    assert.match(view.status.textContent, /Guardando/);
});

test('informa el error si falla la carga de una imagen', async () => {
    const view = page({uploads: [{status: false}]});
    await view.submit();
    assert.equal(view.submits, 0);
    assert.match(view.status.textContent, /imagen sigue sin cargarse/);
    assert.equal(view.button.disabled, false);
});
