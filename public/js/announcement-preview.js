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
    let videoUrl = null;
    let imageSelection = 0;

    const release = kind => {
        if (kind === 'image' && imageUrl) URL.revokeObjectURL(imageUrl);
        if (kind === 'video' && videoUrl) URL.revokeObjectURL(videoUrl);
        if (kind === 'image') imageUrl = null;
        else videoUrl = null;
    };
    const currentImage = () => imageUrl || (removeImage?.checked ? '' : form.dataset.currentImage);
    const currentVideo = () => videoUrl || (removeVideo?.checked ? '' : form.dataset.currentVideo);
    const message = (text, error = false) => {
        status.textContent = text;
        status.classList.toggle('is-error', error);
        submit.disabled = error;
    };
    const renderMedia = () => {
        const image = currentImage();
        const video = currentVideo();
        if (video) {
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
            const placeholder = document.createElement('div');
            placeholder.className = 'portal-announcement-placeholder';
            placeholder.setAttribute('aria-hidden', 'true');
            placeholder.innerHTML = '<span>CCyF</span><strong>Convocatorias</strong>';
            media.replaceChildren(placeholder);
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
    const changed = () => message(visible.checked
        ? 'Vista previa sin guardar. La tarjeta aparecerá en Inicio al guardar.'
        : 'Vista previa sin guardar. La tarjeta seguirá oculta hasta que la actives y guardes.');

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
        release('image');
        const file = imageInput.files?.[0];
        if (!file) { renderMedia(); changed(); return; }
        if (removeImage) removeImage.checked = false;
        if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type) || file.size > 6 * 1024 * 1024) {
            imageInput.value = '';
            renderMedia();
            message('La imagen debe ser PNG, JPG o WebP y medir como máximo 6 MB.', true);
            return;
        }
        const url = URL.createObjectURL(file);
        imageUrl = url;
        const check = new Image();
        check.onload = () => {
            if (version !== imageSelection) return;
            if (check.naturalWidth < 300 || check.naturalHeight < 200
                || check.naturalWidth > 5000 || check.naturalHeight > 5000) {
                release('image');
                imageInput.value = '';
                renderMedia();
                message('La imagen debe medir entre 300 × 200 y 5000 × 5000 píxeles.', true);
                return;
            }
            renderMedia();
            changed();
        };
        check.onerror = () => {
            if (version !== imageSelection) return;
            release('image');
            imageInput.value = '';
            renderMedia();
            message('No se pudo abrir esta imagen. Elige otra.', true);
        };
        check.src = url;
    });
    videoInput.addEventListener('change', () => {
        release('video');
        const file = videoInput.files?.[0];
        if (!file) { renderMedia(); changed(); return; }
        if (removeVideo) removeVideo.checked = false;
        if (!['video/mp4', 'video/webm'].includes(file.type) || file.size > 50 * 1024 * 1024) {
            videoInput.value = '';
            renderMedia();
            message('El video debe ser MP4 o WebM y medir como máximo 50 MB.', true);
            return;
        }
        videoUrl = URL.createObjectURL(file);
        renderMedia();
        changed();
    });
    removeImage?.addEventListener('change', () => {
        ++imageSelection;
        if (removeImage.checked) { imageInput.value = ''; release('image'); }
        renderMedia();
        changed();
    });
    removeVideo?.addEventListener('change', () => {
        if (removeVideo.checked) { videoInput.value = ''; release('video'); }
        renderMedia();
        changed();
    });
    window.addEventListener('pagehide', () => { release('image'); release('video'); });
})();
