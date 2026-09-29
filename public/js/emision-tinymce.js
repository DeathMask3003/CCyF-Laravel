document.addEventListener('DOMContentLoaded', () => {
    const source = document.getElementById('emision-details');
    const form = document.getElementById('emision-form');
    if (!source || !form || !window.tinymce) return;

    const baseUrl = source.dataset.tinyBase.replace(/\/$/, '');
    const token = form.querySelector('input[name="_token"]')?.value;
    const font = document.getElementById('emision-font-family');
    const size = document.getElementById('emision-font-size');
    let submitting = false;

    const uploadImage = async (file) => {
        if (!['image/png', 'image/jpeg'].includes(file.type) || file.size > 2 * 1024 * 1024) {
            throw new Error('Selecciona una imagen PNG o JPG de hasta 2 MB.');
        }
        const data = new FormData();
        data.append('image', file, file.name || 'imagen.png');
        const response = await fetch(source.dataset.uploadUrl, {
            method: 'POST',
            body: data,
            credentials: 'same-origin',
            headers: {'X-CSRF-TOKEN': token, 'Accept': 'application/json'},
        });
        const result = await response.json();
        if (!response.ok || !result.url) {
            throw new Error(result.errors?.image?.[0] || result.message || 'No se pudo cargar la imagen.');
        }
        return result;
    };

    const fontForPreview = () => font?.value === 'dejavuserif' ? 'dejavuserif, Georgia, serif'
        : font?.value === 'freesans' ? 'freesans, Arial, sans-serif' : 'dejavusans, Arial, sans-serif';
    const sizeForPreview = () => `${Number(size?.value) || 9}pt`;
    const contentStyle = `body{font-family:${fontForPreview()};font-size:${sizeForPreview()};line-height:1.45;color:#29242a;max-width:900px;margin:18px auto;padding:0 14px}`
        + 'body>p{text-align:justify} table{border-collapse:collapse;width:100%} th,td{padding:5px;vertical-align:top} img{max-width:100%;height:auto}';

    window.tinymce.init({
        selector: '#emision-details',
        base_url: baseUrl,
        suffix: '.min',
        license_key: 'gpl',
        language: 'es-MX',
        language_url: `${baseUrl}/langs/es-MX.js`,
        height: 660,
        min_height: 420,
        resize: true,
        branding: false,
        promotion: false,
        mobile: {menubar: false, toolbar_mode: 'sliding'},
        plugins: 'autolink lists link image table code fullscreen searchreplace wordcount',
        menubar: 'edit view insert format table tools',
        toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline | alignleft aligncenter alignright alignjustify | bullist numlist | link image table | searchreplace code fullscreen',
        toolbar_mode: 'sliding',
        font_family_formats: 'DejaVu Sans=dejavusans;DejaVu Serif=dejavuserif;FreeSans=freesans',
        font_size_formats: '7pt 8pt 9pt 9.5pt 10pt 11pt 12pt 14pt',
        block_formats: 'Párrafo=p;Título=h2;Subtítulo=h3',
        content_style: contentStyle,
        paste_data_images: true,
        automatic_uploads: true,
        images_file_types: 'png,jpg,jpeg',
        file_picker_types: 'image',
        image_dimensions: true,
        convert_urls: false,
        images_upload_handler: async (blobInfo) => (await uploadImage(blobInfo.blob())).url,
        file_picker_callback: (callback) => {
            const picker = document.createElement('input');
            picker.type = 'file';
            picker.accept = 'image/png,image/jpeg,.png,.jpg,.jpeg';
            picker.addEventListener('change', async () => {
                const file = picker.files?.[0];
                if (!file) return;
                try {
                    const result = await uploadImage(file);
                    callback(result.url, {alt: file.name.slice(0, 120), width: result.width});
                } catch (error) {
                    window.tinymce.get('emision-details')?.notificationManager.open({
                        text: error.message || 'No se pudo cargar la imagen.', type: 'error', timeout: 6000,
                    });
                }
            }, {once: true});
            picker.click();
        },
        setup: (editor) => {
            const updatePreview = () => {
                const body = editor.getBody();
                if (!body) return;
                body.style.fontFamily = fontForPreview();
                body.style.fontSize = sizeForPreview();
            };
            editor.on('init', updatePreview);
            font?.addEventListener('change', updatePreview);
            size?.addEventListener('input', updatePreview);

            form.addEventListener('click', async (event) => {
                const button = event.target.closest?.('button[type="submit"], input[type="submit"]');
                if (!button || button.form !== form) return;
                event.preventDefault();
                if (submitting) return;
                submitting = true;
                try {
                    const uploads = await editor.uploadImages();
                    if (uploads.some((result) => !result.status)) {
                        throw new Error('Una imagen no se cargó. Corrígela antes de guardar o abrir el PDF.');
                    }
                    if (/<img\b[^>]*\bsrc=["'](?:blob:|data:)/i.test(editor.getContent())) {
                        throw new Error('Espera a que se carguen todas las imágenes antes de continuar.');
                    }
                    editor.save();
                    if (!editor.getContent({format: 'text'}).trim()) {
                        editor.notificationManager.open({text: 'Escribe el contenido de la convocatoria.', type: 'error'});
                        editor.focus();
                        return;
                    }
                    form.requestSubmit(button);
                } catch (error) {
                    editor.notificationManager.open({
                        text: error.message || 'Espera a que terminen de cargarse las imágenes.', type: 'error', timeout: 6000,
                    });
                } finally {
                    submitting = false;
                }
            });
            form.addEventListener('submit', () => editor.save());
        },
    }).catch((error) => {
        console.error('No se pudo iniciar TinyMCE.', error);
    });
});
