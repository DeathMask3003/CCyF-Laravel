@extends('layouts.app')

@section('title', 'Mi perfil')
@push('head')
<link rel="stylesheet" href="{{ asset('css/profile-signature.css') }}?v=20260923-2">
@endpush

@section('content')
<div class="page-heading profile-heading">
    <div><span class="eyebrow">Cuenta personal</span><h1>Mi perfil</h1><p>Actualiza tus datos de contacto y protege el acceso a tu cuenta.</p></div>
    <a class="outline-button button-link" href="{{ route('ubicaciones.index') }}">Ver mis ubicaciones</a>
</div>

<div class="profile-summary panel">
    <div class="profile-avatar" aria-hidden="true">@if($photoAvailable)<img src="{{ route('perfil.photo') }}" alt="">@else{{ mb_substr($user->usu_area, 0, 1) }}@endif</div>
    <div><strong>{{ $user->usu_area }}</strong><span>{{ $user->usu_correo }}</span></div>
    <form method="post" action="{{ route('perfil.photo-upload') }}" enctype="multipart/form-data" class="profile-photo-form">@csrf<label for="profile-photo">Foto de perfil</label><input id="profile-photo" name="foto" type="file" accept="image/png,image/jpeg,image/webp" required><button type="submit" class="outline-button">Actualizar foto</button>@error('foto')<small class="field-error">{{ $message }}</small>@enderror</form>
    <span class="pill pill-ready">Cuenta activa</span>
</div>

<div class="profile-layout">
    <section class="panel profile-panel" aria-labelledby="datos-title">
        <div class="profile-panel-heading"><div><span class="eyebrow">Información personal</span><h2 id="datos-title">Datos de mi perfil</h2></div><span class="pill pill-neutral">Solo tu cuenta</span></div>
        <form method="post" action="{{ route('perfil.update') }}">
            @csrf @method('put')
            <div class="profile-fields">
                <div class="full-field"><label for="nombre">Nombre completo <span class="required">*</span></label><input id="nombre" name="nombre" value="{{ old('nombre', $user->usu_area) }}" autocomplete="name" required maxlength="150">@error('nombre')<small class="field-error">{{ $message }}</small>@enderror</div>
                <div><label for="telefono">Teléfono <span class="required">*</span></label><div class="phone-field"><span aria-hidden="true">+52 1</span><input id="telefono" name="telefono" type="tel" inputmode="numeric" autocomplete="tel-national" pattern="[0-9]{10}" maxlength="10" value="{{ old('telefono', $phone) }}" placeholder="7222123456" required></div><small class="field-help">Solo escribe los 10 dígitos. Se guarda con prefijo 521.</small>@error('telefono')<small class="field-error">{{ $message }}</small>@enderror</div>
                <div><label for="correo">Correo de acceso</label><input id="correo" value="{{ $user->usu_correo }}" disabled><small class="field-help">Para cambiar el correo, contacta a administración.</small></div>
                <div><label for="rfc">RFC {{ !in_array((int) $user->rol_id, [2, 3, 18], true) ? '*' : '' }}</label><input id="rfc" name="rfc" value="{{ old('rfc', $user->rfc) }}" maxlength="13" class="uppercase-field" autocomplete="off" placeholder="AAAA000000AAA" @if(!in_array((int) $user->rol_id, [2, 3, 18], true)) required @endif>@error('rfc')<small class="field-error">{{ $message }}</small>@enderror</div>
                <div><label for="curp">CURP {{ !in_array((int) $user->rol_id, [2, 3, 18], true) ? '*' : '' }}</label><input id="curp" name="curp" value="{{ old('curp', $user->curp) }}" maxlength="18" class="uppercase-field" autocomplete="off" placeholder="18 caracteres" @if(!in_array((int) $user->rol_id, [2, 3, 18], true)) required @endif>@error('curp')<small class="field-error">{{ $message }}</small>@enderror</div>
                <div class="full-field"><label for="ine_clave">INE · Clave de elector, OCR o CIC {{ !in_array((int) $user->rol_id, [2, 3, 18], true) ? '*' : '' }}</label><input id="ine_clave" name="ine_clave" value="{{ old('ine_clave', $user->ine_clave) }}" maxlength="18" class="uppercase-field" autocomplete="off" placeholder="Clave de elector (18), OCR (13) o CIC (9)" @if(!in_array((int) $user->rol_id, [2, 3, 18], true)) required @endif><small class="field-help">Si tu dato anterior tiene otro formato, revísalo en tu credencial antes de guardar.</small>@error('ine_clave')<small class="field-error">{{ $message }}</small>@enderror</div>
                <div class="full-field"><label for="direccion">Dirección</label><textarea id="direccion" name="direccion" rows="3" maxlength="400">{{ old('direccion', $user->direcc) }}</textarea>@error('direccion')<small class="field-error">{{ $message }}</small>@enderror</div>
                <div><label for="contacto_alterno">Contacto alterno</label><input id="contacto_alterno" name="contacto_alterno" value="{{ old('contacto_alterno', $user->cont_alter) }}" maxlength="150">@error('contacto_alterno')<small class="field-error">{{ $message }}</small>@enderror</div>
                <div><label for="telefono_alterno">Teléfono alterno</label><div class="phone-field"><span aria-hidden="true">+52 1</span><input id="telefono_alterno" name="telefono_alterno" type="tel" inputmode="numeric" pattern="[0-9]{10}" maxlength="10" value="{{ old('telefono_alterno', $alternatePhone) }}" placeholder="10 dígitos"></div>@error('telefono_alterno')<small class="field-error">{{ $message }}</small>@enderror</div>
                <div class="full-field"><label for="telegram_chat_id">Telegram Chat ID <span class="muted">(opcional)</span></label><input id="telegram_chat_id" name="telegram_chat_id" inputmode="numeric" value="{{ old('telegram_chat_id', $user->telegram_chat_id) }}" maxlength="20">@error('telegram_chat_id')<small class="field-error">{{ $message }}</small>@enderror</div>
            </div>
            <div class="profile-actions"><button class="button" type="submit">Guardar cambios</button></div>
        </form>
    </section>

    <div class="profile-side">
        @if(app(\App\Services\ProductionIntegrations::class)->google())
        <section class="panel profile-panel" aria-labelledby="google-title">
            <span class="eyebrow">Acceso a la cuenta</span><h2 id="google-title">Google</h2>
            <p class="muted">{{ $user->google_sub ? 'Tu cuenta de Google está vinculada.' : 'Vincula el mismo correo de tu perfil para iniciar sesión con Google.' }}</p>
            <a class="outline-button button-link" href="{{ route('perfil.google.link') }}">{{ $user->google_sub ? 'Cambiar cuenta vinculada' : 'Vincular Google' }}</a>
            @error('google')<p class="field-error" role="alert">{{ $message }}</p>@enderror
        </section>
        @endif
        @if ($canManageSignature)
        <section class="panel profile-panel signature-panel" aria-labelledby="signature-title">
            <span class="eyebrow">Uso administrativo</span><h2 id="signature-title">Mi firma</h2>
            <p class="muted">La imagen se utilizará en la hoja final de evaluación cuando tu perfil corresponda a uno de los firmantes.</p>
            <div class="signature-preview" id="signature-preview">
                @if ($signatureAvailable)
                    <img src="{{ route('perfil.signature') }}" alt="Firma actual de {{ $user->usu_area }}" id="signature-image">
                @else
                    <span id="signature-empty">Aún no tienes una imagen de firma cargada.</span>
                @endif
            </div>
            <form method="post" action="{{ route('perfil.signature-upload') }}" enctype="multipart/form-data" class="signature-form">
                @csrf
                <label for="firma">Cargar imagen de firma</label>
                <input id="firma" name="firma" type="file" accept="image/png,image/jpeg,image/webp" required>
                <small class="field-help">PNG, JPG o WEBP, máximo 2 MB. Recomendamos PNG con fondo transparente.</small>
                @error('firma')<small class="field-error">{{ $message }}</small>@enderror
                <button class="button" type="submit">Guardar firma</button>
            </form>
        </section>
        <section class="panel profile-panel sat-panel" aria-labelledby="sat-title">
            <span class="eyebrow">Identidad digital</span><h2 id="sat-title">e.firma del SAT</h2>
            <p class="muted">Carga tu certificado .cer y tu clave .key. Se validan con tu contraseña; la contraseña no se guarda. Los archivos nuevos se conservan en almacenamiento privado.</p>
            @if($satStatus['available'])
                <div class="sat-status"><span class="pill {{ $satStatus['expired'] ? 'pill-pending' : 'pill-ready' }}">{{ $satStatus['expired'] ? 'Certificado vencido' : 'Certificado cargado' }}</span><strong>{{ $satStatus['alias'] }}</strong>
                    @if($satStatus['serial'])<small>Serie: {{ $satStatus['serial'] }}</small>@endif
                    @if($satStatus['validTo'])<small>Vigente hasta: {{ $satStatus['validTo'] }}</small>@endif
                </div>
                <form method="post" action="{{ route('perfil.sat-delete') }}" class="sat-delete-form" onsubmit="return confirm('Se borrarán físicamente tu certificado y tu clave privada del servidor. ¿Continuar?')">@csrf @method('delete')<button class="outline-button" type="submit">Eliminar e.firma y archivos</button>@error('efirma')<small class="field-error">{{ $message }}</small>@enderror</form>
            @else
                <div class="sat-empty">Aún no tienes un certificado y una clave privada cargados.</div>
            @endif
            <form method="post" action="{{ route('perfil.sat-upload') }}" enctype="multipart/form-data" class="sat-upload-form">@csrf
                <label for="sat-alias">Alias</label><input id="sat-alias" name="alias" maxlength="80" value="{{ old('alias', $satStatus['alias']) }}" placeholder="Mi e.firma SAT">@error('alias')<small class="field-error">{{ $message }}</small>@enderror
                <label for="sat-cer">Certificado .cer</label><input id="sat-cer" name="cer" type="file" accept=".cer,.crt,.pem" required>@error('cer')<small class="field-error">{{ $message }}</small>@enderror
                <label for="sat-key">Clave privada cifrada .key</label><input id="sat-key" name="key" type="file" accept=".key,.pem" required>@error('key')<small class="field-error">{{ $message }}</small>@enderror
                <label for="sat-password">Contraseña de la clave</label><input id="sat-password" name="password_sat" type="password" autocomplete="off" required>@error('password_sat')<small class="field-error">{{ $message }}</small>@enderror
                <button class="button" type="submit">Validar y guardar e.firma</button>
            </form>
            @if($satStatus['available'] || $satStatus['imageAvailable'])
                <form method="post" action="{{ route('perfil.signature-method') }}" class="sat-method-form">@csrf @method('put')<strong>Método preferido</strong><div><label><input type="radio" name="metodo" value="efirma" @checked($satStatus['method'] === 'efirma') @disabled(!$satStatus['available'])> e.firma SAT</label><label><input type="radio" name="metodo" value="imagen" @checked($satStatus['method'] === 'imagen') @disabled(!$satStatus['imageAvailable'])> Imagen de firma</label></div><button class="outline-button" type="submit">Guardar preferencia</button>@error('metodo')<small class="field-error">{{ $message }}</small>@enderror</form>
            @endif
            <small class="field-help">Registrar la e.firma no aplica por sí solo una firma criptográfica a los PDF.</small>
        </section>
        @endif
        <section class="panel profile-panel" aria-labelledby="password-title">
            <span class="eyebrow">Seguridad</span><h2 id="password-title">Cambiar contraseña</h2><p class="muted">Usa al menos 10 caracteres, mayúsculas, minúsculas y números.</p>
            <form method="post" action="{{ route('perfil.password') }}" class="profile-password-form">
                @csrf @method('put')
                <div><label for="current_password">Contraseña actual</label><input id="current_password" name="current_password" type="password" autocomplete="current-password" required>@error('current_password')<small class="field-error">{{ $message }}</small>@enderror</div>
                <div><label for="password">Nueva contraseña</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="10" required>@error('password')<small class="field-error">{{ $message }}</small>@enderror</div>
                <div><label for="password_confirmation">Confirmar nueva contraseña</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="10" required></div>
                <button class="button" type="submit">Actualizar contraseña</button>
            </form>
        </section>
        <section class="panel profile-panel" aria-labelledby="connection-title">
            <span class="eyebrow">Sesión actual</span><h2 id="connection-title">Mi conexión</h2>
            <dl class="connection-list"><div><dt>Dirección IP actual</dt><dd>{{ $ip }}</dd></div><div><dt>Navegador</dt><dd class="agent-value">{{ $agent ?: 'No disponible' }}</dd></div><div><dt>Estado</dt><dd><span class="pill pill-ready">Sesión activa</span></dd></div><div><dt>Última ubicación registrada</dt><dd>{{ $lastLocation ? (collect([$lastLocation->ciudad, $lastLocation->region, $lastLocation->pais])->filter()->implode(', ') ?: 'Coordenadas registradas') : 'Sin registro' }}</dd>@if($lastLocation)<small class="field-help">{{ \Illuminate\Support\Carbon::parse($lastLocation->fecha_registro)->format('d/m/Y · H:i') }}</small>@endif</div></dl>
            <p class="field-help">La ubicación aproximada por IP puede ser imprecisa. Puedes guardar tu ubicación actual desde el módulo de ubicaciones.</p>
            <a class="text-link" href="{{ route('ubicaciones.index') }}">Ir a mis ubicaciones →</a>
        </section>
    </div>
</div>
<script>
document.querySelectorAll('.uppercase-field').forEach(el => el.addEventListener('input', () => { const start = el.selectionStart; el.value = el.value.toUpperCase(); el.setSelectionRange(start, start); }));
const signatureInput = document.getElementById('firma');
signatureInput?.addEventListener('change', () => {
    const file = signatureInput.files?.[0];
    if (!file || !file.type.startsWith('image/')) return;
    const preview = document.getElementById('signature-preview');
    let image = document.getElementById('signature-image');
    if (!image) { image = document.createElement('img'); image.id = 'signature-image'; image.alt = 'Vista previa de tu firma'; preview.replaceChildren(image); }
    const url = URL.createObjectURL(file);
    image.onload = () => URL.revokeObjectURL(url);
    image.src = url;
});
</script>
@endsection
