<?php

namespace App\Http\Controllers;

use App\Services\LegacyPasswordVerifier;
use App\Services\MexicanPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        return view('perfil.show', [
            'user' => $request->user(),
            'phone' => MexicanPhone::local($request->user()->usu_telf),
            'alternatePhone' => MexicanPhone::local($request->user()->telf_alter),
            'ip' => $request->ip(),
            'agent' => $request->userAgent(),
            'lastLocation' => DB::table('ccyf_ubicaciones')->where('usu_id', $request->user()->getKey())->orderByDesc('fecha_registro')->first(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->merge([
            'rfc' => mb_strtoupper(trim((string) $request->input('rfc'))),
            'curp' => mb_strtoupper(trim((string) $request->input('curp'))),
            'ine_clave' => mb_strtoupper(trim((string) $request->input('ine_clave'))),
        ]);
        $identityRequired = ! in_array((int) $request->user()->rol_id, [2, 3, 18], true);
        $user = $request->user();
        $identityRule = fn (string $pattern, ?string $previous) => function (string $attribute, mixed $value, \Closure $fail) use ($pattern, $previous): void {
            if (mb_strtoupper((string) $previous) !== $value && ! preg_match($pattern, (string) $value)) {
                $fail('El formato de este identificador no es válido.');
            }
        };
        $data = $request->validate([
            'nombre' => ['required', 'string', 'min:5', 'max:150'],
            'telefono' => ['required', 'regex:/^\d{10}$/'],
            'rfc' => [$identityRequired ? 'required' : 'nullable', $identityRule('/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/u', $user->rfc)],
            'curp' => [$identityRequired ? 'required' : 'nullable', $identityRule('/^[A-Z]{4}\d{6}[HM][A-Z]{5}[A-Z0-9]{2}$/', $user->curp)],
            'ine_clave' => [$identityRequired ? 'required' : 'nullable', $identityRule('/^(?:[A-Z0-9]{18}|\d{9}|\d{13})$/', $user->ine_clave)],
            'direccion' => ['nullable', 'string', 'max:400'],
            'contacto_alterno' => ['nullable', 'string', 'max:150'],
            'telefono_alterno' => ['nullable', 'regex:/^\d{10}$/'],
            'telegram_chat_id' => ['nullable', 'regex:/^\d{5,20}$/'],
        ], [
            'telefono.regex' => 'Escribe solo los 10 dígitos de tu teléfono.',
            'telefono_alterno.regex' => 'Escribe solo los 10 dígitos del teléfono alterno.',
        ]);

        $user->usu_area = trim($data['nombre']);
        $user->usu_telf = MexicanPhone::store($data['telefono']);
        $user->rfc = $data['rfc'] ?? null;
        $user->curp = $data['curp'] ?? null;
        $user->ine_clave = $data['ine_clave'] ?? null;
        $user->direcc = $data['direccion'] ?? null;
        $user->cont_alter = $data['contacto_alterno'] ?? null;
        $user->telf_alter = filled($data['telefono_alterno'] ?? null) ? MexicanPhone::store($data['telefono_alterno']) : null;
        $user->telegram_chat_id = $data['telegram_chat_id'] ?? null;
        $user->save();

        return back()->with('status', 'Tu perfil se actualizó correctamente.');
    }

    public function password(Request $request, LegacyPasswordVerifier $verifier): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(10)->mixedCase()->numbers()],
        ]);

        if (! $verifier->verify($data['current_password'], $request->user()->getAuthPassword())) {
            return back()->withErrors(['current_password' => 'La contraseña actual no es correcta.']);
        }

        $request->user()->usu_pass = Hash::make($data['password']);
        $request->user()->save();
        $request->session()->regenerate();

        return back()->with('status', 'Tu contraseña se actualizó correctamente.');
    }
}
