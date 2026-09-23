function suggestedDesignationEndDate(start) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(start)) return null;
    const [year, month, day] = start.split('-').map(Number);
    const original = new Date(Date.UTC(year, month - 1, day));
    if (original.getUTCFullYear() !== year || original.getUTCMonth() !== month - 1
        || original.getUTCDate() !== day) return null;

    const nextYear = year + 1;
    const lastDay = new Date(Date.UTC(nextYear, month, 0)).getUTCDate();
    const end = new Date(Date.UTC(nextYear, month - 1, Math.min(day, lastDay)));
    const weekday = end.getUTCDay();
    if (weekday === 6) end.setUTCDate(end.getUTCDate() - 1);
    if (weekday === 0) end.setUTCDate(end.getUTCDate() - 2);

    return {
        date: end.toISOString().slice(0, 10),
        movedFrom: weekday === 6 ? 'sábado' : weekday === 0 ? 'domingo' : null,
    };
}

(() => {
    const decision = document.getElementById('decision');
    const designation = document.getElementById('designation-fields');
    if (!decision || !designation) return;

    const start = document.getElementById('fecha-inicio');
    const end = document.getElementById('fecha-fin');
    const confirmed = document.getElementById('fecha-fin-confirmada');
    const help = document.getElementById('fecha-fin-ayuda');
    const reset = document.getElementById('fecha-fin-sugerida');
    const displayDate = value => new Intl.DateTimeFormat('es-MX', {
        dateStyle: 'full', timeZone: 'UTC',
    }).format(new Date(`${value}T00:00:00Z`));

    const useSuggestion = () => {
        const suggestion = suggestedDesignationEndDate(start.value);
        end.min = start.value;
        end.value = suggestion?.date || '';
        confirmed.checked = false;
        reset.disabled = !suggestion;
        help.textContent = !suggestion
            ? 'Selecciona la fecha de inicio para calcular un año. Puedes cambiar la fecha final manualmente.'
            : suggestion.movedFrom
                ? `Un año después cae en ${suggestion.movedFrom}; se recorrió al viernes ${displayDate(suggestion.date)}. Revisa y confirma esta fecha, o cámbiala manualmente.`
                : `Se propuso un año después: ${displayDate(suggestion.date)}. Revisa y confirma esta fecha, o cámbiala manualmente.`;
    };

    const updateDesignation = () => {
        const enabled = decision.value === 'designado';
        designation.hidden = !enabled;
        designation.querySelectorAll('input').forEach(input => { input.required = enabled; });
        if (enabled && start.value && !end.value) useSuggestion();
    };

    start.addEventListener('change', useSuggestion);
    reset.addEventListener('click', useSuggestion);
    end.addEventListener('change', () => {
        confirmed.checked = false;
        if (!end.value) {
            help.textContent = 'Selecciona una fecha de fin y confírmala antes de guardar.';
            return;
        }
        const day = new Date(`${end.value}T00:00:00Z`).getUTCDay();
        help.textContent = `Fecha de fin ajustada manualmente: ${displayDate(end.value)}. ${day === 0 || day === 6
            ? 'Esta fecha cae en fin de semana. ' : ''}Revísala y confirma si es correcta.`;
    });
    decision.addEventListener('change', updateDesignation);
    end.min = start.value;
    reset.disabled = !start.value;
    if (start.value && end.value) {
        help.textContent = `Fecha de fin seleccionada: ${displayDate(end.value)}. Confirma que es correcta.`;
    }
    updateDesignation();
})();
