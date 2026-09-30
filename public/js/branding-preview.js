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
