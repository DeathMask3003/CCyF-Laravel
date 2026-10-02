(() => {
    const form = document.querySelector('[data-branding-form]');
    if (!form) return;

    const titleInput = document.getElementById('brand-title');
    const mottoInput = document.getElementById('brand-motto');
    const fileInput = document.getElementById('brand-logo');
    const removeInput = document.getElementById('brand-remove');
    const uploadPreview = document.getElementById('brand-upload-preview');
    const brandPreview = document.getElementById('brand-preview-logo');
    const status = document.getElementById('brand-logo-status');
    const submit = form.querySelector('[data-branding-submit]');
    const currentLogo = form.dataset.currentLogo;
    const defaultLogo = form.dataset.defaultLogo;
    const acceptedTypes = new Set(['image/png', 'image/jpeg', 'image/webp']);
    const acceptedExtensions = /\.(png|jpe?g|webp)$/i;
    let selection = 0;

    function showPreview(url) {
        uploadPreview.src = url;
        brandPreview.src = url;
    }

    function showStatus(state, heading, detail) {
        status.dataset.state = state;
        status.querySelector('strong').textContent = heading;
        status.querySelector('small').textContent = detail;
        submit.disabled = state === 'invalid' || state === 'checking';
    }

    function reject(reason) {
        showPreview(currentLogo);
        showStatus('invalid', 'No se puede guardar esta imagen', reason);
    }

    titleInput.addEventListener('input', () => {
        document.getElementById('brand-preview-title').textContent = titleInput.value;
    });
    mottoInput.addEventListener('input', () => {
        document.getElementById('brand-preview-motto').textContent = mottoInput.value;
    });

    fileInput.addEventListener('change', () => {
        const version = ++selection;
        const file = fileInput.files[0];
        if (!file) {
            showPreview(removeInput?.checked ? defaultLogo : currentLogo);
            showStatus('neutral', removeInput?.checked ? 'Marca predeterminada' : 'Imagen actual',
                removeInput?.checked ? 'Se usará al guardar los cambios.' : 'No se seleccionó una imagen nueva.');
            return;
        }

        if (removeInput) removeInput.checked = false;
        if (!acceptedExtensions.test(file.name) || (file.type && !acceptedTypes.has(file.type))) {
            reject('Elige un archivo PNG, JPG o WebP.');
            return;
        }
        if (file.size > 4 * 1024 * 1024) {
            reject('El archivo supera el límite de 4 MB.');
            return;
        }

        showStatus('checking', 'Comprobando imagen…', file.name);
        const reader = new FileReader();
        reader.onerror = () => {
            if (version === selection) reject('No se pudo leer el archivo. Elige otra imagen.');
        };
        reader.onload = () => {
            if (version !== selection) return;
            const image = new Image();
            image.onerror = () => {
                if (version === selection) reject('El archivo no contiene una imagen que el navegador pueda mostrar.');
            };
            image.onload = () => {
                if (version !== selection) return;
                const { naturalWidth: width, naturalHeight: height } = image;
                if (width < 80 || height < 80 || width > 4000 || height > 4000) {
                    reject(`La imagen mide ${width} × ${height} píxeles; debe estar entre 80 × 80 y 4000 × 4000.`);
                    return;
                }
                showPreview(reader.result);
                showStatus('valid', 'Imagen lista para guardar',
                    `${file.name} · ${width} × ${height} píxeles · ${(file.size / 1024 / 1024).toFixed(2)} MB`);
            };
            image.src = reader.result;
        };
        reader.readAsDataURL(file);
    });

    removeInput?.addEventListener('change', () => {
        ++selection;
        if (removeInput.checked) fileInput.value = '';
        showPreview(removeInput.checked ? defaultLogo : currentLogo);
        showStatus('neutral', removeInput.checked ? 'Marca predeterminada' : 'Imagen actual',
            removeInput.checked ? 'Se usará al guardar los cambios.' : 'No se seleccionó una imagen nueva.');
    });
})();

(() => {
    const form = document.querySelector('[data-portal-document-form]');
    if (!form) return;

    const fileInput = document.getElementById('document-file');
    const removeInput = document.getElementById('remove-document');
    const status = document.getElementById('document-file-status');
    const preview = document.getElementById('document-preview-link');
    const submit = form.querySelector('[data-document-submit]');
    const currentUrl = form.dataset.currentDocument;
    let temporaryUrl = null;
    let selection = 0;

    const releasePreview = () => {
        if (temporaryUrl) URL.revokeObjectURL(temporaryUrl);
        temporaryUrl = null;
    };
    const showStatus = (state, heading, detail) => {
        status.dataset.state = state;
        status.querySelector('strong').textContent = heading;
        status.querySelector('small').textContent = detail;
        submit.disabled = state === 'invalid' || state === 'checking';
    };
    const showCurrent = () => {
        releasePreview();
        preview.href = currentUrl || '#';
        preview.hidden = !currentUrl || !!removeInput?.checked;
        showStatus('neutral', removeInput?.checked ? 'Se retirará al guardar' : (currentUrl ? 'PDF publicado' : 'Aún no hay PDF publicado'),
            removeInput?.checked ? 'La tarjeta desaparecerá de Inicio.' : (currentUrl ? 'Puedes consultarlo o elegir uno nuevo.' : 'Selecciona un archivo para mostrarlo a los permisionarios.'));
    };

    fileInput.addEventListener('change', async () => {
        const version = ++selection;
        const file = fileInput.files[0];
        if (!file) { showCurrent(); return; }
        if (removeInput) removeInput.checked = false;
        releasePreview();
        preview.hidden = true;
        if (!/\.pdf$/i.test(file.name) || (file.type && file.type !== 'application/pdf')) {
            showStatus('invalid', 'No se puede publicar este archivo', 'Selecciona un PDF válido.');
            return;
        }
        if (file.size > 15 * 1024 * 1024) {
            showStatus('invalid', 'El archivo supera 15 MB', 'Reduce el tamaño del PDF y vuelve a elegirlo.');
            return;
        }
        showStatus('checking', 'Comprobando PDF…', file.name);
        try {
            const signature = await file.slice(0, 5).text();
            if (version !== selection) return;
            if (signature !== '%PDF-') {
                showStatus('invalid', 'No se puede publicar este archivo', 'El contenido no corresponde a un PDF.');
                return;
            }
            temporaryUrl = URL.createObjectURL(file);
            preview.href = temporaryUrl;
            preview.dataset.documentName = file.name;
            preview.hidden = false;
            showStatus('valid', 'PDF listo para publicar', `${file.name} · ${(file.size / 1024 / 1024).toFixed(2)} MB. Revísalo antes de guardar.`);
        } catch {
            if (version === selection) showStatus('invalid', 'No se pudo leer el PDF', 'Vuelve a elegir el archivo.');
        }
    });

    removeInput?.addEventListener('change', () => {
        ++selection;
        if (removeInput.checked) fileInput.value = '';
        showCurrent();
    });
    window.addEventListener('pagehide', releasePreview);
})();
