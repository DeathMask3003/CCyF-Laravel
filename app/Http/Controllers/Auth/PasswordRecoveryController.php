<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LegacyUser;
use App\Services\LegacyMenu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class PasswordRecoveryController extends Controller
{
    public function request(): View
    {
        return view('auth.forgot-password');
    }

    public function email(Request $request, LegacyMenu $menu): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:150']]);
        $email = mb_strtolower(trim($data['email']));

        $users = LegacyUser::query()->whereRaw('LOWER(usu_correo) = ?', [$email])->where('est', true)->get();
        foreach ($users as $user) {
            if ($menu->roleActive($user)) {
                Password::broker()->sendResetLink(['usu_id' => $user->getKey()]);
            }
        }

        return back()->with('status', 'Si el correo está registrado, recibirás un enlace para restablecer tu contraseña.');
    }

    public function edit(string $token, Request $request, LegacyMenu $menu): View
    {
        $account = (int) $request->query('cuenta');
        $user = $account > 0 ? LegacyUser::find($account) : null;
        abort_unless($user && $user->est && $menu->roleActive($user), 404);

        return view('auth.reset-password', [
            'token' => $token,
            'account' => $user->getKey(),
            'email' => $user->usu_correo,
        ]);
    }

    public function update(Request $request, LegacyMenu $menu): RedirectResponse
    {
        $data = $request->validate([
            'cuenta' => ['required', 'integer', 'min:1'],
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', PasswordRule::min(10)->mixedCase()->numbers()],
        ]);

        $user = LegacyUser::find($data['cuenta']);
        if (! $user || ! $user->est || ! $menu->roleActive($user)) {
            return back()->withErrors(['password' => 'El enlace no es válido o ya venció. Solicita uno nuevo.']);
        }

        $status = Password::broker()->reset([
            'usu_id' => $user->getKey(),
            'token' => $data['token'],
            'password' => $data['password'],
        ], function (LegacyUser $user, string $password): void {
            $user->usu_pass = Hash::make($password);
            $user->setRememberToken(Str::random(60));
            $user->save();
        });

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['password' => 'El enlace no es válido o ya venció. Solicita uno nuevo.']);
        }

        return redirect()->route('login')->with('status', 'Contraseña actualizada. Ya puedes iniciar sesión.');
    }
}
