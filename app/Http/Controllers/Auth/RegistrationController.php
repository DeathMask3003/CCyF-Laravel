<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LegacyUser;
use App\Services\MexicanPhone;
use App\Services\TurnstileVerification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegistrationController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request, TurnstileVerification $turnstile): RedirectResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'min:3', 'max:150'],
            'email' => ['required', 'email', 'max:150'],
            'telefono' => ['required', 'regex:/^[0-9]{10}$/'],
            'password' => ['required', 'confirmed', Password::min(10)->mixedCase()->numbers()],
        ], [
            'telefono.regex' => 'Escribe los 10 dígitos de tu teléfono.',
        ]);

        $turnstile->verify($request, 'register');

        $email = mb_strtolower(trim($data['email']));
        $role = DB::table('ccyf_roles')->where('legacy_rol_id', 1)->where('est', true)->first();
        if (! $role) {
            throw ValidationException::withMessages(['email' => 'El registro no está disponible en este momento.']);
        }

        $id = DB::transaction(function () use ($data, $email, $role): int {
            if (DB::table('ccyf_usuarios')->whereRaw('LOWER(usu_correo) = ?', [$email])->exists()) {
                throw ValidationException::withMessages(['email' => 'Este correo ya tiene una cuenta. Inicia sesión o recupera tu contraseña.']);
            }

            return DB::table('ccyf_usuarios')->insertGetId([
                'usu_area' => trim($data['nombre']),
                'usu_correo' => $email,
                'usu_telf' => MexicanPhone::store($data['telefono']),
                'usu_pass' => Hash::make($data['password']),
                'rol_id' => $role->rol_id,
                'est' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ], 'usu_id');
        });

        Auth::login(LegacyUser::findOrFail($id));
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('status', 'Tu cuenta de concursante está lista.');
    }
}
