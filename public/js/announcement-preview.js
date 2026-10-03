(() => {
    const form = document.querySelector('[data-announcement-form]');
    if (!form) return;

    const imageInput = document.getElementById('announcement-image');
    const videoInput = document.getElementById('announcement-video');
    const removeImage = document.getElementById('remove-announcement-image');
    const removeVideo = document.getElementById('remove-announcement-video');
    const media = document.getElementById('announcement-media-preview');
    const title = document.getElementById('announcement-title');
    const description = document.getElementById('announcement-description');
    const links = document.getElementById('announcement-links-preview');
    const status = document.getElementById('announcement-preview-status');
    const submit = form.querySelector('[data-announcement-submit]');
    const visible = document.getElementById('announcement-visible');
    let imageUrl = null;
    let imageSelection = 0;

    const placeholder = () => {
        const element = document.createElement('div');
        element.className = 'portal-announcement-placeholder';
        element.setAttribute('aria-hidden', 'true');
        element.innerHTML = '<span>CCyF</span><strong>Convocatorias</strong>';
        return element;
    };
    const currentImage = () => imageUrl || (removeImage?.checked ? '' : form.dataset.currentImage);
    const currentVideo = () => removeVideo?.checked ? '' : form.dataset.currentVideo;
    const message = (text, error = false) => {
        status.textContent = text;
        status.classList.toggle('is-error', error);
        submit.disabled = error;
    };
    const renderMedia = () => {
        const image = currentImage();
        const video = currentVideo();
        if (videoInput.files?.length) {
            const draft = document.createElement('div');
            draft.className = 'portal-announcement-video-draft';
            if (image) {
                const picture = document.createElement('img');
                picture.src = image;
                picture.alt = 'Portada del video seleccionado';
                draft.append(picture);
            } else {
                draft.append(placeholder());
            }
            const badge = document.createElement('span');
            badge.textContent = 'Video listo para guardar';
            draft.append(badge);
            media.replaceChildren(draft);
        } else if (video) {
            const player = document.createElement('video');
            player.controls = true;
            player.playsInline = true;
            player.preload = 'none';
            if (image) player.poster = image;
            const source = document.createElement('source');
            source.src = video;
            source.type = videoInput.files?.[0]?.type || form.dataset.currentVideoType;
            player.append(source);
            media.replaceChildren(player);
        } else if (image) {
            const picture = document.createElement('img');
            picture.src = image;
            picture.alt = 'Vista previa de la convocatoria';
            media.replaceChildren(picture);
        } else {
            media.replaceChildren(placeholder());
        }
    };
    const renderLinks = () => {
        links.replaceChildren();
        [1, 2].forEach(number => {
            const url = document.getElementById(`announcement-link-${number}-url`).value.trim();
            if (!url) return;
            const label = document.getElementById(`announcement-link-${number}-label`).value.trim()
                || (number === 1 ? 'Consultar convocatoria' : 'Más información');
            const item = document.createElement('span');
            const opensNewTab = document.getElementById(`announcement-link-${number}-blank`).checked;
            item.textContent = `${label}  ${opensNewTab ? '↗' : '→'}`;
            links.append(item);
        });
    };
    const changed = () => {
        const visibility = visible.checked
            ? 'La tarjeta aparecerá en Inicio al guardar.'
            : 'La tarjeta seguirá oculta hasta que la actives y guardes.';
        message(`Vista previa sin guardar. ${visibility}${videoInput.files?.length ? ' El video podrá reproducirse después de guardar.' : ''}`);
    };

    title.addEventListener('input', () => {
        document.getElementById('announcement-title-preview').textContent = title.value || 'Título de la convocatoria';
        changed();
    });
    description.addEventListener('input', () => {
        document.getElementById('announcement-description-preview').textContent = description.value;
        changed();
    });
    visible.addEventListener('change', changed);
    [1, 2].forEach(number => {
        ['label', 'url'].forEach(part => document.getElementById(`announcement-link-${number}-${part}`)
            .addEventListener('input', () => { renderLinks(); changed(); }));
        document.getElementById(`announcement-link-${number}-blank`)
            .addEventListener('change', () => { renderLinks(); changed(); });
    });

    imageInput.addEventListener('change', () => {
        const version = ++imageSelection;
        imageUrl = null;
        const file = imageInput.files?.[0];
        if (!file) { renderMedia(); changed(); return; }
        if (removeImage) removeImage.checked = false;
        if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type) || file.size > 6 * 1024 * 1024) {
            imageInput.value = '';
            renderMedia();
            message('La imagen debe ser PNG, JPG o WebP y medir como máximo 6 MB.', true);
            return;
        }
        const reader = new FileReader();
        reader.onload = () => {
            if (version !== imageSelection) return;
            if (typeof reader.result !== 'string' || !reader.result.startsWith('data:image/')) {
                imageInput.value = '';
                renderMedia();
                message('No se pudo leer la imagen. Elige otra.', true);
                return;
            }
            const check = new Image();
            check.onload = () => {
                if (version !== imageSelection) return;
                if (check.naturalWidth < 300 || check.naturalHeight < 200
                    || check.naturalWidth > 5000 || check.naturalHeight > 5000) {
                    imageInput.value = '';
                    renderMedia();
                    message('La imagen debe medir entre 300 × 200 y 5000 × 5000 píxeles.', true);
                    return;
                }
                imageUrl = reader.result;
                renderMedia();
                changed();
            };
            check.onerror = () => {
                if (version !== imageSelection) return;
                imageInput.value = '';
                renderMedia();
                message('No se pudo abrir esta imagen. Elige otra.', true);
            };
            check.src = reader.result;
        };
        reader.onerror = () => {
            if (version !== imageSelection) return;
            imageInput.value = '';
            renderMedia();
            message('No se pudo leer la imagen. Elige otra.', true);
        };
        reader.readAsDataURL(file);
    });
    videoInput.addEventListener('change', () => {
        const file = videoInput.files?.[0];
        if (!file) { renderMedia(); changed(); return; }
        if (removeVideo) removeVideo.checked = false;
        if (!['video/mp4', 'video/webm'].includes(file.type) || file.size > 50 * 1024 * 1024) {
            videoInput.value = '';
            renderMedia();
            message('El video debe ser MP4 o WebM y medir como máximo 50 MB.', true);
            return;
        }
        renderMedia();
        changed();
    });
    removeImage?.addEventListener('change', () => {
        ++imageSelection;
        if (removeImage.checked) { imageInput.value = ''; imageUrl = null; }
        renderMedia();
        changed();
    });
    removeVideo?.addEventListener('change', () => {
        if (removeVideo.checked) videoInput.value = '';
        renderMedia();
        changed();
    });
})();
