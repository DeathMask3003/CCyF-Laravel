<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LegacyUser;
use App\Services\LegacyMenu;
use App\Services\ProductionIntegrations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleController extends Controller
{
    public function redirect(Request $request, ProductionIntegrations $integrations): RedirectResponse
    {
        abort_unless($integrations->google(), 404);
        $request->session()->put('ccyf.google_mode', 'login');

        return Socialite::driver('google')->redirect();
    }

    public function link(Request $request, ProductionIntegrations $integrations): RedirectResponse
    {
        abort_unless($integrations->google(), 404);
        $request->session()->put('ccyf.google_mode', 'link');
        $request->session()->put('ccyf.google_user', $request->user()->getKey());

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request, ProductionIntegrations $integrations, LegacyMenu $menu): RedirectResponse
    {
        abort_unless($integrations->google(), 404);
        $mode = $request->session()->pull('ccyf.google_mode');
        $linkId = $request->session()->pull('ccyf.google_user');
        if (! in_array($mode, ['login', 'link'], true)) {
            return redirect()->route('login')->withErrors(['email' => 'Vuelve a iniciar el acceso con Google.']);
        }

        try {
            // Socialite valida el estado OAuth y obtiene la identidad desde Google.
            $google = Socialite::driver('google')->user();
        } catch (Throwable) {
            return $this->error($mode, 'No se pudo completar la autenticación con Google. Inténtalo de nuevo.');
        }

        $sub = (string) $google->getId();
        $email = mb_strtolower(trim((string) $google->getEmail()));
        $verified = $google->user['email_verified'] ?? false;
        if ($sub === '' || $email === '' || ! in_array($verified, [true, 'true', 1], true)) {
            return $this->error($mode, 'Google no confirmó un correo verificado para esta cuenta.');
        }

        if ($mode === 'link') {
            $local = $request->user();
            if (! $local || (int) $local->getKey() !== (int) $linkId || ! $local->est || ! $menu->roleActive($local)) {
                return redirect()->route('login')->withErrors(['email' => 'Vuelve a iniciar sesión para vincular Google.']);
            }
            if (mb_strtolower(trim((string) $local->usu_correo)) !== $email) {
                return redirect()->route('perfil.show')->withErrors(['google' => 'El correo de Google debe coincidir con el de tu perfil.']);
            }
            if (LegacyUser::query()->where('google_sub', $sub)->where('usu_id', '!=', $local->getKey())->exists()) {
                return redirect()->route('perfil.show')->withErrors(['google' => 'Esta cuenta de Google ya está vinculada con otro usuario.']);
            }
            try {
                DB::table('ccyf_usuarios')->where('usu_id', $local->getKey())->update(['google_sub' => $sub, 'updated_at' => now()]);
            } catch (Throwable) {
                return redirect()->route('perfil.show')->withErrors(['google' => 'No se pudo vincular Google. Inténtalo de nuevo.']);
            }

            return redirect()->route('perfil.show')->with('status', 'Tu cuenta de Google quedó vinculada.');
        }

        $local = LegacyUser::query()->where('google_sub', $sub)->first();
        if (! $local && $this->authoritativeEmail($email, $google->user)) {
            $matches = LegacyUser::query()->whereRaw('LOWER(usu_correo) = ?', [$email])->limit(2)->get();
            if ($matches->count() === 1 && blank($matches->first()->google_sub)
                && $matches->first()->est && $menu->roleActive($matches->first())) {
                $local = $matches->first();
                try {
                    DB::table('ccyf_usuarios')->where('usu_id', $local->getKey())->whereNull('google_sub')
                        ->update(['google_sub' => $sub, 'updated_at' => now()]);
                    $local->refresh();
                } catch (Throwable) {
                    return $this->error($mode, 'No se pudo vincular Google. Inicia sesión con contraseña.');
                }
            }
        }

        if (! $local || ! $local->est || ! $menu->roleActive($local) || $local->google_sub !== $sub) {
            return $this->error($mode, 'Esta cuenta aún no está vinculada. Inicia sesión con contraseña y vincula Google desde Mi perfil.');
        }

        Auth::login($local);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    private function authoritativeEmail(string $email, array $raw): bool
    {
        $domain = strtolower((string) strrchr($email, '@'));
        if ($domain === '@gmail.com') {
            return true;
        }

        $hostedDomain = strtolower((string) ($raw['hd'] ?? ''));

        return $hostedDomain !== '' && $domain === '@'.$hostedDomain;
    }

    private function error(string $mode, string $message): RedirectResponse
    {
        return redirect()->route($mode === 'link' ? 'perfil.show' : 'login')
            ->withErrors([$mode === 'link' ? 'google' : 'email' => $message]);
    }
}
