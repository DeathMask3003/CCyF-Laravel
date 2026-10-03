document.addEventListener('DOMContentLoaded', () => {
    const notice = document.getElementById('ccyf-connectivity');
    if (!notice) return;

    const title = notice.querySelector('[data-connectivity-title]');
    const detail = notice.querySelector('[data-connectivity-detail]');
    const retry = notice.querySelector('[data-connectivity-retry]');
    let state = 'checking';
    let probe = null;
    let nextCheck = null;
    let recoveryMessage = null;

    const display = (next) => {
        const previous = state;
        state = next;
        notice.hidden = false;
        notice.dataset.state = next;
        retry.hidden = next === 'online' || next === 'recovered' || next === 'checking';
        clearTimeout(recoveryMessage);

        if (next === 'offline') {
            title.textContent = 'Sin conexión a Internet';
            detail.textContent = 'Tus datos siguen en esta página. Revisa tu red antes de guardar.';
        } else if (next === 'server') {
            title.textContent = 'CCyF no responde';
            detail.textContent = 'No logramos contactar al servidor. Conserva esta página abierta e inténtalo de nuevo.';
        } else if (next === 'recovered' || (next === 'online' && ['offline', 'server'].includes(previous))) {
            state = 'recovered';
            notice.dataset.state = 'recovered';
            title.textContent = 'Conexión restablecida';
            detail.textContent = 'Ya puedes continuar y guardar tus cambios.';
            recoveryMessage = setTimeout(() => display('online'), 5000);
        } else {
            title.textContent = 'Conexión activa';
            detail.textContent = '';
        }
    };

    const schedule = () => {
        clearTimeout(nextCheck);
        if (!document.hidden) nextCheck = setTimeout(check, state === 'online' ? 30000 : 10000);
    };

    const check = async () => {
        clearTimeout(nextCheck);
        probe?.abort();
        if (!navigator.onLine) {
            probe = null;
            display('offline');
            schedule();
            return;
        }

        const current = new AbortController();
        probe = current;
        const timeout = setTimeout(() => current.abort(), 6000);
        try {
            const response = await fetch(notice.dataset.checkUrl, {
                method: 'HEAD', cache: 'no-store', credentials: 'same-origin', signal: current.signal,
            });
            if (probe === current) display(response.status === 204 ? 'online' : 'server');
        } catch {
            if (probe === current) display(navigator.onLine ? 'server' : 'offline');
        } finally {
            clearTimeout(timeout);
            if (probe === current) schedule();
        }
    };

    retry.addEventListener('click', check);
    window.addEventListener('offline', check);
    window.addEventListener('online', check);
    window.addEventListener('pageshow', check);
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) clearTimeout(nextCheck);
        else check();
    });
    document.addEventListener('submit', (event) => {
        if (navigator.onLine && !['offline', 'server'].includes(state)) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        display(navigator.onLine ? 'server' : 'offline');
        notice.focus();
    }, true);

    check();
});
