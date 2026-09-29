<div class="emision-editor-toolbar" role="toolbar" aria-label="Formato del contenido">
    <button type="button" data-command="bold" title="Negritas"><strong>N</strong></button>
    <button type="button" data-command="italic" title="Cursivas"><em>C</em></button>
    <button type="button" data-command="underline" title="Subrayado"><u>S</u></button>
    <button type="button" data-command="insertUnorderedList" title="Lista con viñetas">• Lista</button>
    <button type="button" data-command="insertOrderedList" title="Lista numerada">1. Lista</button>
    <button type="button" data-command="formatBlock" data-value="h2" title="Encabezado">Título</button>
    <span class="emision-toolbar-divider" aria-hidden="true"></span>
    <span class="emision-toolbar-label">Texto seleccionado</span>
    <select id="emision-selection-font" aria-label="Fuente del texto seleccionado">
        <option value="">Conservar fuente</option>
        <option value="dejavusans">DejaVu Sans</option>
        <option value="dejavuserif">DejaVu Serif</option>
        <option value="freesans">FreeSans</option>
    </select>
    <select id="emision-selection-size" aria-label="Tamaño del texto seleccionado">
        <option value="">Conservar tamaño</option>
        @foreach ([7, 8, 9, 9.5, 10, 11, 12, 14] as $pointSize)<option value="{{ $pointSize }}">{{ $pointSize }} pt</option>@endforeach
    </select>
    <button type="button" data-apply-text-style title="Aplicar letra y tamaño al texto seleccionado">Aplicar</button>
    <span class="emision-toolbar-divider" aria-hidden="true"></span>
    <button type="button" data-insert-image title="Insertar imagen o código QR">＋ Imagen / QR</button>
    <input id="emision-image-file" type="file" accept="image/png,image/jpeg,.png,.jpg,.jpeg" hidden data-upload-url="{{ route('emision.images.upload') }}" aria-label="Seleccionar imagen PNG o JPG">
    <label class="emision-image-width" for="emision-image-width" hidden>Ancho <input id="emision-image-width" type="range" min="60" max="480" step="10" value="180"><output for="emision-image-width">180 px</output></label>
    <span class="emision-image-status" role="status" aria-live="polite"></span>
    <span class="emision-toolbar-divider" aria-hidden="true"></span>
    <select id="emision-table-rows" aria-label="Filas de la nueva tabla"><option value="2">2 filas</option><option value="3">3 filas</option><option value="4">4 filas</option><option value="5">5 filas</option><option value="6">6 filas</option></select>
    <select id="emision-table-columns" aria-label="Columnas de la nueva tabla"><option value="2">2 columnas</option><option value="3">3 columnas</option><option value="4">4 columnas</option></select>
    <button type="button" data-insert-table title="Insertar tabla editable en la posición del cursor">＋ Tabla</button>
    <span class="emision-rich-table-tools" hidden><button type="button" data-add-row>＋ Fila</button><button type="button" data-add-column>＋ Columna</button><button type="button" data-remove-table>Eliminar tabla</button></span>
    <span class="emision-toolbar-divider" aria-hidden="true"></span>
    <button type="button" data-command="justifyLeft" aria-label="Alinear a la izquierda" title="Alinear a la izquierda">Izquierda</button>
    <button type="button" data-command="justifyCenter" aria-label="Centrar texto" title="Centrar texto">Centrar</button>
    <button type="button" data-command="justifyRight" aria-label="Alinear a la derecha" title="Alinear a la derecha">Derecha</button>
    <button type="button" data-command="justifyFull" aria-label="Justificar texto" title="Justificar texto">Justificar</button>
</div>
