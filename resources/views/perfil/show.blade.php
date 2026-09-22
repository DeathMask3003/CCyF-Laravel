@extends('layouts.app')

@section('title', 'Mi perfil')

@section('content')
<div class="page-heading profile-heading">
    <div><span class="eyebrow">Cuenta personal</span><h1>Mi perfil</h1><p>Actualiza tus datos de contacto y protege el acceso a tu cuenta.</p></div>
    <a class="outline-button button-link" href="{{ route('ubicaciones.index') }}">Ver mis ubicaciones</a>
</div>

<div class="profile-summary panel">
    <div class="profile-avatar" aria-hidden="true">{{ mb_substr($user->usu_area, 0, 1) }}</div>
    <div><strong>{{ $user->usu_area }}</strong><span>{{ $user->usu_correo }}</span></div>
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
<script>document.querySelectorAll('.uppercase-field').forEach(el => el.addEventListener('input', () => { const start = el.selectionStart; el.value = el.value.toUpperCase(); el.setSelectionRange(start, start); }));</script>
@endsection
