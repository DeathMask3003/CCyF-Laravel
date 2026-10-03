const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const script = fs.readFileSync(path.join(__dirname, '../../public/js/connectivity.js'), 'utf8');

function page({reachable = true, online = true} = {}) {
    const windowEvents = {};
    const documentEvents = {};
    const title = {textContent: ''};
    const detail = {textContent: ''};
    const retry = {hidden: true, addEventListener(name, handler) { this[name] = handler; }};
    const notice = {
        hidden: true, dataset: {checkUrl: '/estado-conexion'},
        querySelector(selector) {
            return {'[data-connectivity-title]': title, '[data-connectivity-detail]': detail,
                '[data-connectivity-retry]': retry}[selector];
        },
        focus() { this.focused = true; },
    };
    const navigator = {onLine: online};
    const requests = [];
    const document = {
        hidden: false,
        getElementById: () => notice,
        addEventListener(name, handler) { documentEvents[name] = handler; },
    };
    const window = {addEventListener(name, handler) { windowEvents[name] = handler; }};
    vm.runInNewContext(script, {
        document, window, navigator, AbortController,
        fetch: async (url, options) => {
            requests.push({url, options});
            if (!reachable) throw new Error('network unavailable');
            return {status: 204};
        },
        setTimeout: () => 1, clearTimeout() {},
    });
    documentEvents.DOMContentLoaded();
    return {
        notice, title, detail, retry, navigator, requests, windowEvents, documentEvents,
        setReachable(value) { reachable = value; },
    };
}

test('comprueba el servidor y muestra la conexión activa', async () => {
    const view = page();
    await new Promise(setImmediate);
    assert.equal(view.notice.dataset.state, 'online');
    assert.equal(view.title.textContent, 'Conexión activa');
    assert.equal(view.requests[0].url, '/estado-conexion');
    assert.equal(view.requests[0].options.method, 'HEAD');
});

test('sin internet avisa y evita perder un formulario', async () => {
    const view = page();
    await new Promise(setImmediate);
    view.navigator.onLine = false;
    await view.windowEvents.offline();
    let cancelled = false;
    let stopped = false;
    view.documentEvents.submit({
        preventDefault() { cancelled = true; },
        stopImmediatePropagation() { stopped = true; },
    });
    assert.equal(view.notice.dataset.state, 'offline');
    assert.match(view.title.textContent, /Sin conexión/);
    assert.equal(view.retry.hidden, false);
    assert.equal(cancelled, true);
    assert.equal(stopped, true);
    assert.equal(view.notice.focused, true);

    view.navigator.onLine = true;
    await view.windowEvents.online();
    assert.equal(view.notice.dataset.state, 'recovered');
    assert.match(view.title.textContent, /restablecida/);
});

test('distingue una caída del servidor de la falta de internet', async () => {
    const view = page({reachable: false});
    await new Promise(setImmediate);
    assert.equal(view.notice.dataset.state, 'server');
    assert.equal(view.title.textContent, 'CCyF no responde');
    view.setReachable(true);
    await view.retry.click();
    assert.equal(view.notice.dataset.state, 'recovered');
});
