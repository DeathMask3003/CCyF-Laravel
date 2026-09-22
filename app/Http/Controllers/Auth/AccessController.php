<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LegacyUser;
use App\Services\LegacyPasswordVerifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

class AccessController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request, LegacyPasswordVerifier $passwords): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:50'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        try {
            $user = LegacyUser::query()
                ->where('usu_correo', trim($credentials['email']))
                ->where('est', 1)
                ->first();
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['email' => 'El acceso aún no está disponible.'])->onlyInput('email');
        }

        if (! $user || ! $passwords->verify($credentials['password'], $user->getAuthPassword())) {
            return back()->withErrors(['email' => 'El correo o la contraseña no son correctos.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        $request->session()->put('ccyf.pending_user', (int) $user->getKey());
        $request->session()->put('ccyf.pending_until', now()->addMinutes(10)->timestamp);

        if (! $this->sendCode($request, $user)) {
            $this->clearPending($request);

            return back()->withErrors(['email' => 'No se pudo enviar el código. Intenta más tarde.'])->onlyInput('email');
        }

        return redirect()->route('mfa.challenge');
    }

    public function challenge(Request $request): View|RedirectResponse
    {
        if (! $this->hasPending($request)) {
            return redirect()->route('login');
        }

        return view('auth.challenge');
    }

    public function verify(Request $request): RedirectResponse
    {
        $input = $request->validate(['code' => ['required', 'digits:6']]);

        if (! $this->hasPending($request)) {
            $this->clearPending($request);

            return redirect()->route('login');
        }

        $attempts = (int) $request->session()->get('ccyf.code_attempts', 0) + 1;
        $request->session()->put('ccyf.code_attempts', $attempts);
        $storedHash = (string) $request->session()->get('ccyf.code_hash', '');

        if ($attempts > 5 || $storedHash === '' || ! hash_equals($storedHash, $this->codeHash($input['code']))) {
            if ($attempts >= 5) {
                $this->clearPending($request);

                return redirect()->route('login')->withErrors(['email' => 'La verificación expiró. Inicia sesión de nuevo.']);
            }

            return back()->withErrors(['code' => 'El código no es correcto.']);
        }

        try {
            $user = LegacyUser::query()
                ->whereKey((int) $request->session()->get('ccyf.pending_user'))
                ->where('est', 1)
                ->first();
        } catch (Throwable $exception) {
            report($exception);
            $user = null;
        }

        $this->clearPending($request);

        if (! $user) {
            return redirect()->route('login')->withErrors(['email' => 'La cuenta no está disponible.']);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function resend(Request $request): RedirectResponse
    {
        if (! $this->hasPending($request)) {
            return redirect()->route('login');
        }

        try {
            $user = LegacyUser::query()
                ->whereKey((int) $request->session()->get('ccyf.pending_user'))
                ->where('est', 1)
                ->first();
        } catch (Throwable $exception) {
            report($exception);
            $user = null;
        }

        if (! $user || ! $this->sendCode($request, $user)) {
            return back()->withErrors(['code' => 'No se pudo reenviar el código.']);
        }

        return back()->with('status', 'Enviamos un código nuevo a tu correo.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $this->clearPending($request);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function sendCode(Request $request, LegacyUser $user): bool
    {
        $code = (string) random_int(100000, 999999);

        try {
            Mail::raw(
                "Tu código de acceso a CCyF es {$code}. Caduca en 10 minutos. Si no intentaste ingresar, ignora este mensaje.",
                fn ($message) => $message->to($user->usu_correo)->subject('Código de acceso a CCyF'),
            );
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }

        $request->session()->put('ccyf.code_hash', $this->codeHash($code));
        $request->session()->put('ccyf.code_attempts', 0);
        $request->session()->put('ccyf.pending_until', now()->addMinutes(10)->timestamp);

        return true;
    }

    private function hasPending(Request $request): bool
    {
        return $request->session()->has('ccyf.pending_user')
            && (int) $request->session()->get('ccyf.pending_until', 0) > now()->timestamp;
    }

    private function clearPending(Request $request): void
    {
        $request->session()->forget([
            'ccyf.pending_user',
            'ccyf.pending_until',
            'ccyf.code_hash',
            'ccyf.code_attempts',
        ]);
    }

    private function codeHash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
